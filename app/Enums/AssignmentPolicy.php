<?php

namespace App\Enums;

/**
 * FR-SUR-004 / 10.6: how a survey behaves at a site.
 *
 * Mandatory modes are refused while the site's integration is not verified —
 * see App\Services\Surveys\ResolveSurvey for the fail-safe check.
 */
enum AssignmentPolicy: string
{
    case Optional = 'optional';
    case RequiredBeforeAccess = 'required_before_access';
    case PostSession = 'post_session';

    public function label(): string
    {
        return match ($this) {
            self::Optional => 'Optional',
            self::RequiredBeforeAccess => 'Required before access',
            self::PostSession => 'Post-session invitation',
        };
    }

    public function blocksAccess(): bool
    {
        return $this === self::RequiredBeforeAccess;
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
