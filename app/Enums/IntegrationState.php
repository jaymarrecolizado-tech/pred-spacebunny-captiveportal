<?php

namespace App\Enums;

/**
 * FR-OPS-001 for a gateway integration.
 */
enum IntegrationState: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Disabled => 'Disabled',
            self::Revoked => 'Revoked',
        };
    }

    public function acceptsRequests(): bool
    {
        return $this === self::Active;
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
