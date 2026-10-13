<?php

namespace App\Filament\Widgets;

use App\Enums\AttributionMethod;
use App\Models\Site;
use App\Models\SurveyResponse;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * FR-REP-003: averages are always shown next to a count so a small sample is
 * never mistaken for a reliable trend.
 */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $responses = SurveyResponse::query()->visibleTo($user);

        $count = (clone $responses)->count();
        $average = (float) (clone $responses)->whereNotNull('overall_rating')->avg('overall_rating');
        $verified = (clone $responses)->where('attribution_method', AttributionMethod::CaptiveVerified->value)->count();

        return [
            Stat::make('Sites in scope', (string) Site::query()->visibleTo($user)->count())
                ->description('Rows your account may see')
                ->color('slate'),

            Stat::make('Responses', (string) $count)
                ->description('All time, in your scope')
                ->color('primary'),

            Stat::make('Average rating', $count === 0 ? '—' : number_format($average, 2))
                ->description($count === 0 ? 'No responses yet' : "Based on {$count} responses")
                ->color('success'),

            // FR-REP-006: unverified submissions are never presented as verified.
            Stat::make('Verified portal responses', (string) $verified)
                ->description('Captive portal attribution not yet enabled')
                ->color($verified === 0 ? 'gray' : 'success'),
        ];
    }
}
