<?php

namespace App\Enums;

/**
 * How often a site must put the survey in front of a returning device.
 *
 * Per site, so DICT can pilot one location on "every 7 days" while leaving
 * another on "always". Never is also how a location is switched off without
 * deleting it.
 */
enum SurveyTriggerMode: string
{
    /** Show the survey on every connection. The behaviour before this enum existed. */
    case Always = 'always';

    /** Show the survey at most once per trigger_days for a device that already answered. */
    case Interval = 'interval';

    /** Never interrupt. The location stays registered and keeps reporting, but no device is ever asked. */
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::Always => 'Ask every time someone connects',
            self::Interval => 'Ask once every N days',
            self::Never => 'Never ask (location switched off)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Always => 'Every connection must complete the survey before getting internet access.',
            self::Interval => 'Someone who answered recently gets straight back in without seeing the survey. A device that has never answered is always asked.',
            self::Never => 'No survey is shown at this location. Useful for places you are not surveying yet.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
