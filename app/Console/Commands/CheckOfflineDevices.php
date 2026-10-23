<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Services\LicenseAlertMessages;
use App\Services\TelegramNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('licenses:check-offline')]
#[Description('Notify admins (Telegram) about usable licenses whose bot stopped sending heartbeats')]
class CheckOfflineDevices extends Command
{
    /** Keeps each message well below Telegram's 4096 character limit. */
    private const PER_MESSAGE = 8;

    public function handle(TelegramNotifier $telegram): int
    {
        $reported = 0;

        License::offline()
            ->whereNull('offline_notified_at')
            ->whereStatus(LicenseStatus::Active)
            ->with(['product', 'user'])
            ->orderBy('last_seen_at')
            ->get()
            ->chunk(self::PER_MESSAGE)
            ->each(function ($chunk) use ($telegram, &$reported) {
                if ($telegram->send(LicenseAlertMessages::offline($chunk))) {
                    License::whereKey($chunk->modelKeys())->update(['offline_notified_at' => now()]);
                    $reported += $chunk->count();
                }
            });

        if ($reported > 0) {
            $this->components->info("{$reported} offline account(s) reported.");
        }

        return self::SUCCESS;
    }
}
