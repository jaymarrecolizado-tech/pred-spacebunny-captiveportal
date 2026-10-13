<?php

namespace App\Enums;

/**
 * Lifecycle of one captive-portal session (Spec 11.2).
 */
enum PortalSessionState: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Expired = 'expired';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
            self::Abandoned => 'Abandoned',
        };
    }
}
