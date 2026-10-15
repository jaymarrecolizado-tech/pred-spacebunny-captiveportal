<?php

namespace App\Http\Controllers\Reporting;

use App\Services\Reporting\KpiReport;
use App\Services\Reporting\ReportFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Printable survey report (FR-REP-007).
 *
 * Renders server-side to plain HTML so the browser prints it cleanly — no
 * Livewire, no panel sidebar, and no JavaScript to fight the print stylesheet.
 * The user saves it as a PDF from the browser's own print dialog, which means
 * no PDF rendering engine is added to the deployment.
 *
 * Scope and filters come from the same KpiReport service the on-screen report
 * uses, so this document can never show more than the signed-in user is
 * permitted to see, and the two can never disagree about which rows are in
 * scope (FR-REP-005).
 */
class SurveyReportController extends Controller
{
    public function __invoke(Request $request, KpiReport $report): View
    {
        $filters = ReportFilters::fromArray(
            $request->query(),
            (int) config('reporting.positive_rating_threshold'),
        );

        $user = Auth::user();

        // Same service, same filters, same scope rules as the KPI page.
        $summary = collect($report->for($user, $filters))->except('filters')->all();

        // The appendix is one continuous table and CSS breaks the printed pages.
        // Paginating it would only produce page links, which do nothing once the
        // browser is printing - the reader would have to print each screenful
        // separately. Very large sets are capped and the cap is stated on the
        // document; the CSV export remains the way to get every row.
        $cap = (int) config('reporting.pdf_appendix_max_rows');
        $scoped = $report->query($user, $filters);
        $totalMatched = (clone $scoped)->count();

        $responses = $scoped
            ->with(['site:id,site_code,name,region,province,municipality', 'supplier:id,name', 'surveyVersion:id,version_number'])
            ->latest('submitted_at')
            ->limit($cap)
            ->get();

        return view('reporting.survey-report', [
            'totals' => $summary['totals'],
            'bySite' => $summary['by_site'],
            'bySupplier' => $summary['by_supplier'],
            'byIssue' => $summary['by_issue'],
            'responses' => $responses,
            'totalMatched' => $totalMatched,
            'truncated' => $totalMatched > $responses->count(),
            'activeFilters' => array_filter($request->query()),
            'generatedAt' => now(),
            'generatedBy' => $user->email,
            'positiveThreshold' => (int) config('reporting.positive_rating_threshold'),
            'minimumSampleSize' => (int) config('reporting.minimum_sample_size'),
        ]);
    }
}
