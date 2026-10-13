<?php

namespace App\Enums;

/**
 * FR-SUR-003: published versions are immutable; edits create a new version.
 */
enum SurveyVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Retired => 'Retired',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
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
