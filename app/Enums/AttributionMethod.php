<?php

namespace App\Enums;

/**
 * FR-RESP-007: how a response was attributed to its site.
 *
 * Reports must never present SiteLink/QrCode as verified connectivity
 * (FR-REP-006, Spec section 5.3).
 */
enum AttributionMethod: string
{
    case CaptiveVerified = 'captive_verified';
    case SiteLink = 'site_link';
    case QrCode = 'qr_code';
    case AdminImport = 'admin_import';

    public function label(): string
    {
        return match ($this) {
            self::CaptiveVerified => 'Verified captive portal',
            self::SiteLink => 'Site link (unverified)',
            self::QrCode => 'QR code (unverified)',
            self::AdminImport => 'Admin import',
        };
    }

    /**
     * Only a validated gateway handoff may claim verified attribution.
     */
    public function isVerified(): bool
    {
        return $this === self::CaptiveVerified;
    }

    public static function unverified(): array
    {
        return [self::SiteLink, self::QrCode];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
