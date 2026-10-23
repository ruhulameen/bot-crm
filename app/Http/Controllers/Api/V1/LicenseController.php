<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LicenseAuthRequest;
use App\Http\Resources\Api\V1\LicenseResource;
use App\Jobs\SendTelegramMessage;
use App\Services\LicenseAlertMessages;
use App\Services\LicenseAuthenticator;

class LicenseController extends Controller
{
    /**
     * Called when the bot starts: validates the account and records the first sign-in.
     */
    public function login(LicenseAuthRequest $request, LicenseAuthenticator $authenticator): LicenseResource
    {
        return $this->seen($request, $authenticator, notifyFirstLogin: true);
    }

    /**
     * Keep-alive, about once a minute; also tells the bot whether it may keep running.
     */
    public function heartbeat(LicenseAuthRequest $request, LicenseAuthenticator $authenticator): LicenseResource
    {
        return $this->seen($request, $authenticator, notifyFirstLogin: false);
    }

    private function seen(LicenseAuthRequest $request, LicenseAuthenticator $authenticator, bool $notifyFirstLogin): LicenseResource
    {
        $license = $authenticator->attempt(
            $request->validated('product'),
            $request->validated('username'),
            $request->validated('password'),
            $request->ip(),
        );

        $firstLogin = ! $license->hasBeenSeen();
        $wasReportedOffline = $license->offline_notified_at !== null;
        $offlineSince = $license->last_seen_at;

        $license->update(['last_seen_at' => now(), 'offline_notified_at' => null]);

        if ($firstLogin && $notifyFirstLogin) {
            SendTelegramMessage::dispatchAfterResponse(LicenseAlertMessages::firstLogin($license));
        } elseif ($wasReportedOffline) {
            SendTelegramMessage::dispatchAfterResponse(LicenseAlertMessages::backOnline($license, $offlineSince));
        }

        return new LicenseResource($license);
    }
}
