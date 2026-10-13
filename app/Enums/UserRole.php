<?php

namespace App\Enums;

/**
 * Spec section 4.2. Role names are proposals pending DICT confirmation.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case DictAdmin = 'dict_admin';
    case Analyst = 'analyst';
    case SupplierAdmin = 'supplier_admin';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::DictAdmin => 'DICT Administrator',
            self::Analyst => 'Regional/Provincial Analyst',
            self::SupplierAdmin => 'Supplier Administrator',
            self::Auditor => 'Auditor / Read-only Reviewer',
        };
    }

    /**
     * Registry maintenance (suppliers, sites) is DICT-side work.
     */
    public function managesRegistry(): bool
    {
        return in_array($this, [self::SuperAdmin, self::DictAdmin], true);
    }

    public function managesSurveys(): bool
    {
        return in_array($this, [self::SuperAdmin, self::DictAdmin], true);
    }

    public function managesUsers(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Responses are never mutated through the admin UI in this phase
     * (FR-RESP-012 correction/quarantine workflow is a later approval step).
     */
    public function isReadOnly(): bool
    {
        return in_array($this, [self::Analyst, self::Auditor], true);
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
