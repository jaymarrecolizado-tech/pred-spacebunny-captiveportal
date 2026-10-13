<?php

namespace App\Enums;

/**
 * FR-ADM-002: row-level organizational scope. Enforced in policies and query
 * filters (App\Models\Concerns\VisibleToScope), never only by hiding menus.
 */
enum ScopeType: string
{
    case All = 'all';
    case Supplier = 'supplier';
    case Province = 'province';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All sites',
            self::Supplier => 'Own supplier only',
            self::Province => 'Assigned province(s) only',
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
