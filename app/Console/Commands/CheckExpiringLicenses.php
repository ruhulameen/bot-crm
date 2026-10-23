<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Services\LicenseAlertMessages;
use App\Services\TelegramNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('licenses:check-expiring')]
#[Description('Notify admins (Telegram) once about licenses that are about to expire')]
class CheckExpiringLicenses extends Command
{
    /** Keeps each message well below Telegram's 4096 character limit. */
    private const PER_MESSAGE = 8;

    public function handle(TelegramNotifier $telegram): int
    {
        $reported = 0;

        License::expiringSoon()
            ->whereNull('expiry_notified_at')
            ->with(['product', 'user'])
            ->orderBy('expires_at')
            ->get()
            ->chunk(self::PER_MESSAGE)
            ->each(function ($chunk) use ($telegram, &$reported) {
                if ($telegram->send(LicenseAlertMessages::expiring($chunk))) {
                    License::whereKey($chunk->modelKeys())->update(['expiry_notified_at' => now()]);
                    $reported += $chunk->count();
                }
            });

        if ($reported > 0) {
            $this->components->info("{$reported} expiring license(s) reported.");
        }

        return self::SUCCESS;
    }
}
