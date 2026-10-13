<?php

namespace App\Enums;

/**
 * FR-OPS-001: integration lifecycle for a site.
 */
enum IntegrationStatus: string
{
    case Draft = 'draft';
    case Configured = 'configured';
    case Testing = 'testing';
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Configured => 'Configured',
            self::Testing => 'Testing',
            self::Active => 'Active',
            self::Degraded => 'Degraded',
            self::Disabled => 'Disabled',
        };
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
