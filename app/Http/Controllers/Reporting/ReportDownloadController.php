<?php

namespace App\Http\Controllers\Reporting;

use App\Services\Reporting\ReportDocument;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportWriter;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable survey report in CSV, spreadsheet or XML.
 *
 * Every format is the same ReportDocument the PDF renders, so a figure quoted
 * from the spreadsheet is the figure in the printed report (FR-REP-005).
 *
 * Gated by Filament's Authenticate middleware, so a deactivated account is
 * refused here exactly as it is in the panel.
 */
class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, ReportDocument $document): Response
    {
        $format = strtolower((string) $request->query('format', ReportWriter::CSV));

        // An unknown format falls back to CSV rather than erroring: the download
        // button is a link, and a stale bookmark should still produce a file.
        if (! in_array($format, ReportWriter::formats(), true)) {
            $format = ReportWriter::CSV;
        }

        $filters = ReportFilters::fromArray(
            $request->query(),
            (int) config('reporting.positive_rating_threshold'),
        );

        $built = $document->build(Auth::user(), $filters);

        $filename = sprintf(
            'dict-survey-report-%s-%s.%s',
            $filters->from?->format('Ymd') ?? 'all',
            $filters->to?->format('Ymd') ?? 'all',
            $format,
        );

        return response(ReportWriter::write($format, $built), 200, [
            'Content-Type' => ReportWriter::mimeType($format),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Middleware applied to this route.
     *
     * @return array<int, class-string>
     */
    public static function routeMiddleware(): array
    {
        return [Authenticate::class];
    }
}
