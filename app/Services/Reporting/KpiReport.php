<?php

namespace App\Services\Reporting;

use App\Enums\AttributionMethod;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Surveys\DemographicQuestions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The reporting metrics defined in Spec section 13.
 *
 * Two rules are enforced everywhere, not left to the view:
 *  - rows come from `visibleTo()`, so a report can never show more than the
 *    signed-in user is allowed to see (FR-REP-005);
 *  - an average is never returned without the sample size it came from
 *    (FR-REP-003), and per-site figures below the minimum sample size are
 *    flagged rather than presented as meaningful.
 */
class KpiReport
{
    public function for(User $user, ReportFilters $filters): array
    {
        $base = $this->query($user, $filters);

        $responses = (clone $base)->count();
        $rated = (clone $base)->whereNotNull('overall_rating')->count();
        $positive = (clone $base)
            ->where('overall_rating', '>=', $filters->positiveThreshold)
            ->whereNotNull('overall_rating')
            ->count();
        $noIssue = (clone $base)->whereIn('issue_category', config('reporting.no_issue_categories'))->count();
        $withIssue = (clone $base)->whereNotNull('issue_category')->count();
        $issueCount = $withIssue - $noIssue;

        // Distinct connected devices, counted from the hashed client MAC that the
        // Omada handoff already carries (SHA-256, never the raw address).
        //
        // This is a DEVICE count, not a people count: one person with a phone and
        // a tablet is 2, and someone whose connection drops and re-associates is
        // 2. It also only covers the captive-portal cohort - QR and site-link
        // respondents never present a MAC, so they are excluded rather than
        // silently counted as one anonymous user each (FR-REP-006).
        $deviceResponses = (clone $base)->whereNotNull('session_reference_hash')->count();
        $distinctDevices = (clone $base)
            ->whereNotNull('session_reference_hash')
            ->distinct()
            ->count('session_reference_hash');

        return [
            'filters' => $filters,
            'totals' => [
                'responses' => $responses,
                // Sample size for every average below.
                'rated' => $rated,
                'average_rating' => $rated === 0 ? null : round((float) (clone $base)->whereNotNull('overall_rating')->avg('overall_rating'), 2),
                'positive_responses' => $positive,
                'positive_rate' => $rated === 0 ? null : round($positive / $rated * 100, 1),
                'issues_reported' => $issueCount,
                // FR-REP-006: verified and unverified never blended silently.
                'verified' => (clone $base)->where('attribution_method', AttributionMethod::CaptiveVerified->value)->count(),
                'unverified' => (clone $base)->where('attribution_method', '!=', AttributionMethod::CaptiveVerified->value)->count(),
                'issue_rate' => $responses === 0 ? null : round($issueCount / $responses * 100, 1),
                // Device count, and how many responses it is drawn from, so the
                // number can never be read as "people served".
                'distinct_devices' => $distinctDevices,
                'device_responses' => $deviceResponses,
            ],
            // Where each site is, so a reader can tell POP-01 from POP-02 without
            // a second lookup. Municipality and province only - the full
            // address and coordinates are site registry data, not report data.
            'by_site' => $this->group(
                $base,
                'site_id',
                Site::query()->pluck('site_code', 'id'),
                Site::query()
                    ->get(['id', 'municipality', 'province'])
                    ->mapWithKeys(fn (Site $site) => [
                        $site->id => collect([$site->municipality, $site->province])
                            ->filter()
                            ->unique()
                            ->implode(', ') ?: null,
                    ]),
            ),
            'by_supplier' => $this->group($base, 'supplier_id', DB::table('suppliers')->pluck('name', 'id')),
            'by_issue' => $this->issueBreakdown($base),
            'daily' => $this->daily($base),
            // The shape of opinion behind the average. An average of 3.9 from ten
            // people and 3.9 from a thousand look identical as a number and
            // nothing alike as a distribution, so the summary chart shows the split.
            'rating_distribution' => $this->ratingDistribution($base),
            // Who answered. The point of asking: who is actually using the free
            // Wi-Fi, and which sectors DICT is reaching.
            'by_gender' => $this->demographicBreakdown($base, 'gender', $responses),
            'by_sector' => $this->demographicBreakdown($base, 'sector', $responses),
        ];
    }

    /**
     * How many people gave each star rating, always five entries.
     *
     * Zero buckets are returned rather than omitted so the chart keeps a stable
     * axis, and "nobody gave two stars" stays visible as a finding instead of
     * disappearing from the chart.
     *
     * @param  Builder<SurveyResponse>  $base
     * @return array<int, array{stars: int, total: int}>
     */
    private function ratingDistribution(Builder $base): array
    {
        $counts = (clone $base)
            ->whereNotNull('overall_rating')
            ->selectRaw('overall_rating, count(*) as total')
            ->groupBy('overall_rating')
            ->pluck('total', 'overall_rating');

        return collect(range(1, 5))
            ->map(fn (int $star) => [
                'stars' => $star,
                'total' => (int) ($counts[$star] ?? 0),
            ])
            ->all();
    }

    /**
     * How the respondents described themselves.
     *
     * "Did not answer" is a row of its own, and it is always the last one.
     * Leaving it out would turn a survey most people declined into a chart
     * that looks unanimous - the opposite of what the data says.
     *
     * The rows come out in the order the survey asks them, not in order of
     * count, so two reports of the same period can be read side by side.
     *
     * @param  Builder<SurveyResponse>  $base
     * @return array<int, array<string, mixed>>
     */
    private function demographicBreakdown(Builder $base, string $column, int $responses): array
    {
        $counts = (clone $base)
            ->whereNotNull($column)
            ->selectRaw($column.' as value, count(*) as total')
            ->groupBy($column)
            ->pluck('total', 'value');

        $labels = DemographicQuestions::optionLabels()[$column] ?? [];

        // Every option is listed, even the ones nobody chose, so the chart has
        // a stable shape and "nobody is a farmer" stays visible as a finding
        // instead of disappearing from the report.
        $rows = [];

        foreach (array_keys($labels) as $value) {
            $rows[$value] = [
                'value' => $value,
                'label' => $labels[$value],
                'total' => (int) ($counts[$value] ?? 0),
                'share' => $responses === 0 ? null : round((int) ($counts[$value] ?? 0) / $responses * 100, 1),
            ];
        }

        // Anything the report has not seen before still has to be counted.
        foreach ($counts as $value => $total) {
            if (isset($rows[$value])) {
                continue;
            }

            $rows[$value] = [
                'value' => (string) $value,
                'label' => ucfirst(str_replace('_', ' ', (string) $value)),
                'total' => (int) $total,
                'share' => $responses === 0 ? null : round((int) $total / $responses * 100, 1),
            ];
        }

        $notAnswered = (clone $base)->whereNull($column)->count();

        $rows[] = [
            'value' => '',
            'label' => 'Did not answer',
            'total' => $notAnswered,
            'share' => $responses === 0 ? null : round($notAnswered / $responses * 100, 1),
        ];

        return array_values($rows);
    }

    /**
     * The response rows a report covers, after scope and filters.
     *
     * Public so the PDF and the on-screen report read the same rows. Filters can
     * narrow this set but never widen it — `visibleTo()` is applied first, so a
     * filter asking for another province still returns nothing.
     *
     * @return Builder<SurveyResponse>
     */
    public function query(User $user, ReportFilters $filters): Builder
    {
        // Scope first, filters second: a filter can narrow, never widen.
        $query = SurveyResponse::query()->visibleTo($user);

        return $query
            ->when($filters->from, fn (Builder $q) => $q->where('submitted_at', '>=', $filters->from))
            ->when($filters->to, fn (Builder $q) => $q->where('submitted_at', '<=', $filters->to))
            ->when($filters->attribution, fn (Builder $q) => $q->where('attribution_method', $filters->attribution->value))
            ->when($filters->supplierId, fn (Builder $q) => $q->where('supplier_id', $filters->supplierId))
            ->when($filters->siteId, fn (Builder $q) => $q->where('site_id', $filters->siteId))
            ->when($filters->province, fn (Builder $q) => $q->whereHas(
                'site',
                fn (Builder $site) => $site->where('province', $filters->province),
            ));
    }

    /**
     * @param  Builder<SurveyResponse>  $base
     * @param  Collection<int, string>  $labels
     * @return array<int, array<string, mixed>>
     */
    private function group(Builder $base, string $column, Collection $labels, Collection $locations = new Collection): array
    {
        $minimum = (int) config('reporting.minimum_sample_size');

        // Cloned: adding a SELECT and a GROUP BY to the shared builder would
        // leak into every breakdown that follows and quietly change what each
        // one counts.
        return (clone $base)
            ->selectRaw($column.' as group_id, count(*) as responses, count(overall_rating) as rated, avg(overall_rating) as average_rating')
            ->groupBy($column)
            ->orderByDesc('responses')
            ->get()
            ->map(fn ($row) => [
                'label' => $labels[$row->group_id] ?? 'Unknown',
                'location' => $locations->get($row->group_id),
                'responses' => (int) $row->responses,
                'rated' => (int) $row->rated,
                'average_rating' => $row->rated > 0 ? round((float) $row->average_rating, 2) : null,
                'significant' => (int) $row->rated >= $minimum,
            ])
            ->all();
    }

    /**
     * @param  Builder<SurveyResponse>  $base
     * @return array<int, array<string, mixed>>
     */
    private function issueBreakdown(Builder $base): array
    {
        return (clone $base)
            ->whereNotNull('issue_category')
            ->selectRaw('issue_category, count(*) as total')
            ->groupBy('issue_category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'issue_category' => $row->issue_category,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @param  Builder<SurveyResponse>  $base
     * @return array<int, array<string, mixed>>
     */
    private function daily(Builder $base): array
    {
        return (clone $base)
            ->selectRaw('DATE(submitted_at) as day, count(*) as responses, count(overall_rating) as rated, avg(overall_rating) as average_rating')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'responses' => (int) $row->responses,
                'rated' => (int) $row->rated,
                'average_rating' => $row->rated > 0 ? round((float) $row->average_rating, 2) : null,
            ])
            ->all();
    }
}
