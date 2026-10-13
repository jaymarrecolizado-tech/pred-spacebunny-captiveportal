<?php

namespace App\Enums;

/**
 * FR-SUR-005: a survey can be activated, scheduled (draft), paused or retired.
 */
enum SurveyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Retired => 'Retired',
        };
    }

    /**
     * Only an active survey may accept public submissions (FR-RESP-002).
     */
    public function acceptsResponses(): bool
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
