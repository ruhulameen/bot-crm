<?php

namespace App\Enums;

enum LeadType: string
{
    case Bell = 'bell';
    case Insert = 'insert';
    case Otp = 'otp';
    case Selection = 'selection';
    case TapCode = 'tap_code';

    public function label(): string
    {
        return match ($this) {
            self::Bell => 'Bell',
            self::Insert => 'Insert',
            self::Otp => 'OTP',
            self::Selection => 'Selection',
            self::TapCode => 'Tap code',
        };
    }

    /**
     * Forgiving parser for spreadsheet input: "Tap Code", "tap-code", "sel", "OTP code" ...
     */
    public static function fromInput(?string $input): ?self
    {
        $normalised = str((string) $input)->lower()->trim()->replaceMatches('/[\s\-]+/', '_')->toString();

        return match ($normalised) {
            'bell' => self::Bell,
            'insert', 'ins' => self::Insert,
            'otp', 'otp_code' => self::Otp,
            'selection', 'sel' => self::Selection,
            'tap_code', 'tap' => self::TapCode,
            default => null,
        };
    }
}
