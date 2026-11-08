<?php

namespace App\Services\Sites;

use App\Models\Site;
use App\Models\SiteAction;
use App\Models\SpeedSiteSample;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Calculates the same per-site health record for the performance map and the
 * action-required queue.
 */
class SiteHealth
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function cards(User $user): array
    {
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
            $responseForSite = $responseStats->get($site->id, ['responses' => 0, 'rated' => 0, 'average_rating' => null, 'issue_count' => 0, 'issue_rate' => null]);
            $sample = $latestSamples->get($site->id);
            $average = $responseForSite['average_rating'];
            $issueRate = $responseForSite['issue_rate'];
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
            $hasPortal = $site->portal_last_seen_at !== null;
            $hasFeedbackOrLink = ($responseForSite['responses'] ?? 0) > 0 || $sample !== null;

            $health = match (true) {
                ! $hasPortal && ! $hasFeedbackOrLink => 'nodata',
                $sampleError !== null,
                $average !== null && $average < 3.0,
                $issueRate !== null && $issueRate > 40 => 'critical',
                $average !== null && $average < 4.0,
                $issueRate !== null && $issueRate > 20,
                $lowRate >= 2 => 'warning',
                default => $hasPortal ? 'good' : 'nodata',
            };

            $searchable = mb_strtolower(implode(' ', array_filter([
                $site->site_code,
                $site->name,
                $site->municipality,
                $site->province,
                $site->supplier?->name,
            ])));

            $cards[] = [
                'site' => $site,
                'supplier' => $site->supplier?->name,
                'responses' => $responseForSite['responses'],
                'rated' => $responseForSite['rated'],
                'average_rating' => $average,
                'issue_rate' => $issueRate,
                'portal_seen' => $hasPortal,
                'low_rate' => $lowRate,
                'sample_error' => $sampleError,
                'score' => $surveyScore + $issueScore + $linkScore,
                'health' => $health,
                'searchable' => $searchable,
            ];
        }

        usort($cards, fn (array $a, array $b): int => $this->rank($a['health']) <=> $this->rank($b['health']) ?: $a['score'] <=> $b['score'] ?: strcmp($a['site']->site_code, $b['site']->site_code));

        return $cards;
    }

    /**
     * Attach operational action state to an already-calculated card array.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    public function withActions(array $cards): array
    {
        $siteIds = collect($cards)->map(fn (array $card): int => (int) $card['site']->id)->all();

        if ($siteIds === []) {
            return $cards;
        }

        $actions = SiteAction::query()
            ->with('owner:id,name')
            ->whereIn('site_id', $siteIds)
            ->get()
            ->keyBy('site_id');

        foreach ($cards as &$card) {
            $action = $actions->get((int) $card['site']->id);

            $card['action'] = $action === null ? null : [
                'id' => $action->id,
                'owner' => $action->owner?->name,
                'status' => $action->status->value,
                'status_label' => $action->status->label(),
                'due_date' => $action->due_date?->format('Y-m-d'),
            ];
        }

        unset($card);

        return $cards;
    }

    public function rank(string $health): int
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
