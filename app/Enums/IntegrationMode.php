<?php

namespace App\Enums;

/**
 * How responses reach the platform for a site.
 *
 * Note: only CaptivePortal can ever produce AttributionMethod::CaptiveVerified,
 * and that requires a validated gateway handoff (Phase 5). QrCode/SiteLink are
 * explicitly unverified per FR-RESP-006.
 */
enum IntegrationMode: string
{
    case CaptivePortal = 'captive_portal';
    case QrCode = 'qr_code';
    case SiteLink = 'site_link';

    public function label(): string
    {
        return match ($this) {
            self::CaptivePortal => 'Captive portal',
            self::QrCode => 'QR code',
            self::SiteLink => 'Site link',
        };
    }

    public function isVerifiedCapable(): bool
    {
        return $this === self::CaptivePortal;
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
