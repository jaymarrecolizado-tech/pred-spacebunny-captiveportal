<?php

namespace App\Services\Reporting;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One report, assembled once, for every output format.
 *
 * The PDF, the CSV, the spreadsheet and the XML all read this same object, so
 * they cannot drift apart and cannot disagree about which rows are in scope
 * (FR-REP-005). Each format is only a renderer on top.
 *
 * Structure follows the briefing: the summary comes first and the supporting
 * response-level data follows it.
 */
class ReportDocument
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ReportFilters $filters): array
    {
        $report = app(KpiReport::class);
        $summary = collect($report->for($user, $filters))->except('filters')->all();

        // Same filtered, scoped query the figures were computed from.
        $scoped = $report->query($user, $filters);
        $totalMatched = (clone $scoped)->count();

        return [
            'meta' => [
                'generated_at' => now(),
                'generated_by' => $user->email,
                'filters' => $filters->toArray(),
                'positive_threshold' => (int) config('reporting.positive_rating_threshold'),
                'minimum_sample_size' => (int) config('reporting.minimum_sample_size'),
                'pdf_appendix_max_rows' => (int) config('reporting.pdf_appendix_max_rows'),
            ],

            // The first thing every format shows.
            'summary' => $this->summaryLines($summary['totals'], (int) config('reporting.positive_rating_threshold')),

            // Supporting breakdowns.
            'by_site' => $summary['by_site'],
            'by_supplier' => $summary['by_supplier'],
            'by_issue' => $summary['by_issue'],
            'daily' => $summary['daily'],

            // Supporting row-level data, same order and cap as the PDF.
            'total_matched' => $totalMatched,
            'truncated' => $totalMatched > min($totalMatched, (int) config('reporting.pdf_appendix_max_rows')),
            'responses' => $scoped
                ->with(['site:id,site_code,name,region,province,municipality', 'supplier:id,name', 'surveyVersion:id,version_number'])
                ->latest('submitted_at')
                ->limit((int) config('reporting.pdf_appendix_max_rows'))
                ->get(),
        ];
    }

    /**
     * Headline figures as ordered label/value pairs.
     *
     * Every average carries the sample size it came from, and a figure with no
     * data behind it is null rather than a confident 0% (FR-REP-003).
     *
     * @param  array<string, mixed>  $totals
     * @return array<int, array{label: string, value: string|int|null, note: string}>
     */
    private function summaryLines(array $totals, int $threshold): array
    {
        $rated = (int) ($totals['rated'] ?? 0);
        $deviceResponses = (int) ($totals['device_responses'] ?? 0);

        return [
            [
                'label' => 'Total responses',
                'value' => (int) ($totals['responses'] ?? 0),
                'note' => 'All responses matching the filters',
            ],
            [
                'label' => 'Average rating',
                'value' => $totals['average_rating'] ?? null,
                'note' => $rated === 0
                    ? 'No rated responses yet'
                    : sprintf('From %d rated response%s', $rated, $rated === 1 ? '' : 's'),
            ],
            [
                'label' => 'Positive responses',
                'value' => ($totals['positive_rate'] ?? null) === null ? null : $totals['positive_rate'].'%',
                'note' => sprintf('Rating of %d or more', $threshold),
            ],
            [
                'label' => 'Connection issue rate',
                'value' => ($totals['issue_rate'] ?? null) === null ? null : $totals['issue_rate'].'%',
                'note' => 'Share of responses reporting a connection problem',
            ],
            [
                'label' => 'Verified portal responses',
                'value' => (int) ($totals['verified'] ?? 0),
                'note' => 'Confirmed by the captive portal handoff',
            ],
            [
                'label' => 'Site link / QR responses',
                'value' => (int) ($totals['unverified'] ?? 0),
                'note' => 'Self-attributed, not tied to a device',
            ],
            [
                'label' => 'Distinct devices',
                'value' => (int) ($totals['distinct_devices'] ?? 0),
                'note' => sprintf(
                    'Devices, not people, from %d portal response%s. A person with a phone and a tablet counts twice; QR respondents are excluded.',
                    $deviceResponses,
                    $deviceResponses === 1 ? '' : 's',
                ),
            ],
        ];
    }

    /**
     * Column headings for the supporting response table, shared by every format.
     *
     * @return array<int, string>
     */
    public static function responseColumns(): array
    {
        return [
            'reference', 'submitted_at', 'site_code', 'site_name', 'municipality', 'province',
            'supplier', 'survey_version', 'rating', 'issue', 'attribution',
        ];
    }

    /**
     * One response as a flat row keyed by responseColumns().
     *
     * @return array<string, string|int|null>
     */
    public static function responseRow(SurveyResponse $response): array
    {
        return [
            'reference' => substr((string) $response->id, 0, 8),
            'submitted_at' => $response->submitted_at?->format('Y-m-d H:i:s'),
            'site_code' => $response->site?->site_code,
            'site_name' => $response->site?->name,
            'municipality' => $response->site?->municipality,
            'province' => $response->site?->province,
            'supplier' => $response->supplier?->name,
            'survey_version' => $response->surveyVersion?->version_number,
            'rating' => $response->overall_rating,
            'issue' => $response->issue_category,
            'attribution' => $response->attribution_method->label(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public static function siteLocation(Collection $rows): string
    {
        return collect($rows)
            ->pluck('location')
            ->filter()
            ->unique()
            ->implode('; ');
    }
}
