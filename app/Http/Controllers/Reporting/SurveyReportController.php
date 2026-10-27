<?php

namespace App\Http\Controllers\Reporting;

use App\Services\Reporting\ReportDocument;
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
    public function __invoke(Request $request, ReportDocument $document): View
    {
        $filters = ReportFilters::fromArray(
            $request->query(),
            (int) config('reporting.positive_rating_threshold'),
        );

        // The PDF renders the same ReportDocument every download uses, so the
        // figures, the charts and the comments cannot drift apart between
        // formats.
        $built = $document->build(Auth::user(), $filters);
        $summary = $built;

        $responses = $built['responses'];
        $totalMatched = $built['total_matched'];

        return view('reporting.survey-report', [
            'summary' => $summary['summary'],
            'totals' => $summary['totals'],
            'bySite' => $summary['by_site'],
            'bySupplier' => $summary['by_supplier'],
            'byIssue' => $summary['by_issue'],
            // Series the summary-page charts are drawn from.
            'ratingDistribution' => $summary['rating_distribution'],
            'daily' => $summary['daily'],
            // Who answered.
            'byGender' => $summary['by_gender'],
            'bySector' => $summary['by_sector'],
            // Comments grouped by location.
            'commentsBySite' => $summary['comments_by_site'],
            // Free-text answers, printed before the response table.
            'comments' => $summary['comments'],
            'responses' => $responses,
            'totalMatched' => $totalMatched,
            'truncated' => $summary['truncated'],
            'activeFilters' => $summary['meta']['filters'] ? array_filter($summary['meta']['filters']) : [],
            'generatedAt' => $summary['meta']['generated_at'],
            'generatedBy' => $summary['meta']['generated_by'],
            'positiveThreshold' => $summary['meta']['positive_threshold'],
            'minimumSampleSize' => $summary['meta']['minimum_sample_size'],
        ]);
    }
}
