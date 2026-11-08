<?php

namespace App\Filament\Pages;

use App\Models\Site;
use App\Models\SpeedSiteSample;
use App\Models\SurveyResponse;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * A colour-coded view of every location the user may see.
 *
 * This deliberately combines three separate questions instead of inventing a
 * single "network health" verdict without evidence:
 *
 * - What people report (average rating and issue rate)
 * - What devices report (latest timed link-rate sample)
 * - Whether the portal has ever reached the site
 *
 * The score is a guide for triage, not a claim about internet bandwidth or
 * service-level compliance.
 */
class SitePerformancePage extends Page
{
    protected string $view = 'filament.pages.site-performance';

    protected static ?string $navigationLabel = 'Performance map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $title = 'Site performance map';

    protected ?string $subheading = 'Find the locations that need attention first.';

    protected static ?int $sort = 5;

    public string $search = '';

    public string $province = '';

    public string $health = '';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $sites = Site::query()
            ->visibleTo($user)
            ->with('supplier:id,name')
            ->orderBy('site_code')
            ->get();

        $siteIds = $sites->pluck('id');
        $responses = SurveyResponse::query()
            ->visibleTo($user)
            ->whereIn('site_id', $siteIds)
            ->get(['site_id', 'overall_rating', 'issue_category']);

        $responseStats = $responses->groupBy('site_id')->map(function (Collection $rows): array {
            $rated = $rows->whereNotNull('overall_rating');
            $total = $rows->count();
            $noIssue = config('reporting.no_issue_categories', ['none']);
            $withResponse = $rows->whereNotNull('issue_category');
            $issues = $withResponse->filter(fn (SurveyResponse $row): bool => ! in_array($row->issue_category, $noIssue, true))->count();

            return [
                'responses' => $total,
                'rated' => $rated->count(),
                'average_rating' => $rated->isEmpty() ? null : round((float) $rated->avg('overall_rating'), 2),
                'issue_count' => $issues,
                'issue_rate' => $total === 0 ? null : round($issues / $total * 100, 1),
            ];
        });

        $latestTimes = SpeedSiteSample::query()
            ->whereIn('site_id', $siteIds)
            ->selectRaw('site_id, MAX(captured_at) as latest_at')
            ->groupBy('site_id')
            ->pluck('latest_at', 'site_id');

        $latestSamples = $latestTimes->isEmpty()
            ? collect()
            : SpeedSiteSample::query()
                ->whereIn('site_id', $siteIds)
                ->whereIn('captured_at', $latestTimes->values())
                ->get()
                ->unique('site_id')
                ->keyBy('site_id');

        $cards = [];

        foreach ($sites as $site) {
            $responsesForSite = $responseStats->get($site->id, ['responses' => 0, 'rated' => 0, 'average_rating' => null, 'issue_count' => 0, 'issue_rate' => null]);
            $sample = $latestSamples->get($site->id);
            $average = $responsesForSite['average_rating'];
            $issueRate = $responsesForSite['issue_rate'];
            $lowRate = $sample?->low_rate_wireless_count ?? 0;
            $sampleError = $sample?->error;

            $surveyScore = $average === null ? 25 : (int) round(($average / 5) * 50);
            $issueScore = $issueRate === null ? 15 : (int) round(max(0, 1 - ($issueRate / 100)) * 30);
            $linkScore = $sampleError !== null ? 0 : match (true) {
                $lowRate >= 5 => 0,
                $lowRate >= 3 => 5,
                $lowRate >= 1 => 12,
                default => 20,
            };
            $score = $surveyScore + $issueScore + $linkScore;
            $hasPortal = $site->portal_last_seen_at !== null;
            $hasFeedbackOrLink = ($responsesForSite['responses'] ?? 0) > 0 || $sample !== null;

            $health = match (true) {
                ! $hasPortal && ! $hasFeedbackOrLink => 'nodata',
                $sampleError !== null, $average !== null && $average < 3.0, $issueRate !== null && $issueRate > 40 => 'critical',
                $average !== null && $average < 4.0, $issueRate !== null && $issueRate > 20, $lowRate >= 2 => 'warning',
                default => $hasPortal ? 'good' : 'nodata',
            };

            $searchable = mb_strtolower(implode(' ', array_filter([$site->site_code, $site->name, $site->municipality, $site->province, $site->supplier?->name])));

            $cards[] = [
                'site' => $site,
                'supplier' => $site->supplier?->name,
                'responses' => $responsesForSite['responses'],
                'rated' => $responsesForSite['rated'],
                'average_rating' => $average,
                'issue_rate' => $issueRate,
                'portal_seen' => $hasPortal,
                'low_rate' => $lowRate,
                'sample_error' => $sampleError,
                'score' => $score,
                'health' => $health,
                'searchable' => $searchable,
            ];
        }

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $cards = array_values(array_filter($cards, fn (array $card): bool => str_contains($card['searchable'], $needle)));
        }

        if ($this->province !== '') {
            $cards = array_values(array_filter($cards, fn (array $card): bool => $card['site']->province === $this->province));
        }

        if ($this->health !== '') {
            $cards = array_values(array_filter($cards, fn (array $card): bool => $card['health'] === $this->health));
        }

        usort($cards, fn (array $a, array $b): int => $this->healthRank($a['health']) <=> $this->healthRank($b['health']) ?: $a['score'] <=> $b['score'] ?: strcmp($a['site']->site_code, $b['site']->site_code));

        $healthOrder = ['critical', 'warning', 'good', 'nodata'];
        $counts = array_count_values(array_column($cards, 'health'));

        return [
            'cards' => $cards,
            'provinces' => $sites->pluck('province')->filter()->unique()->sort()->values()->all(),
            'counts' => [
                'critical' => $counts['critical'] ?? 0,
                'warning' => $counts['warning'] ?? 0,
                'good' => $counts['good'] ?? 0,
                'nodata' => $counts['nodata'] ?? 0,
            ],
            'healthOrder' => $healthOrder,
        ];
    }

    private function healthRank(string $health): int
    {
        return match ($health) {
            'critical' => 0,
            'warning' => 1,
            'good' => 2,
            'nodata' => 3,
            default => 4,
        };
    }
}
