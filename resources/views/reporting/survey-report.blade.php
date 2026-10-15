{{--
    Printable survey report.

    Deliberately self-contained plain CSS rather than Filament/Tailwind: this
    page exists to be printed, so it must not depend on panel assets, theme
    colour variables, or a stylesheet that could change underneath it. A PDF
    generated last year should still look like the document DICT approved.

    Scope comes from the controller (KpiReport service), never from this view.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Survey report {{ $activeFilters['from'] ?? '' }} to {{ $activeFilters['to'] ?? '' }}</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #d1d5db;
            --soft: #f3f4f6;
            --flag: #92400e;
            --flag-bg: #fef3c7;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: var(--ink);
            background: #fff;
        }

        h1 { font-size: 19px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid var(--line); }
        .sub { color: var(--muted); margin: 0 0 18px; }

        /* Headline figures */
        .tiles { display: flex; flex-wrap: wrap; gap: 8px; }
        .tile { flex: 1 1 150px; border: 1px solid var(--line); padding: 8px 10px; }
        .tile .label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .tile .value { font-size: 21px; font-weight: 700; line-height: 1.15; margin-top: 2px; }
        .tile .note { font-size: 9.5px; color: var(--muted); margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid var(--line); padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: var(--soft); font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        td.num, th.num { text-align: right; white-space: nowrap; }

        .flag { display: inline-block; background: var(--flag-bg); color: var(--flag); font-size: 9px; padding: 0 4px; border-radius: 2px; }
        .muted { color: var(--muted); }
        .small { font-size: 9.5px; }

        /* Two columns for the breakdown tables, but appendix rows stay full width */
        .cols { display: flex; gap: 14px; }
        .cols > * { flex: 1 1 0; min-width: 0; }

        .page { margin-bottom: 16px; }

        /* Screen-only helper; never printed */
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; align-items: center; }
        .toolbar button {
            font: inherit; font-weight: 600; padding: 7px 14px; border: 0;
            background: var(--ink); color: #fff; cursor: pointer;
        }
        .toolbar .hint { color: var(--muted); }

        @page { size: A4; margin: 14mm 12mm; }

        @media print {
            body { padding: 0; font-size: 9.5px; }
            .toolbar { display: none; }

            /* Repeat the column headings on every printed page */
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }

            /* Never split a single response across two pages */
            tr, .tile { page-break-inside: avoid; break-inside: avoid; }

            h2 { page-break-after: avoid; break-after: avoid; }
            .page { page-break-after: always; break-after: page; }
            .page:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Save as PDF</button>
    <span class="hint">In the print dialog choose <strong>Save as PDF</strong> as the destination. Margins and scale are handled by the page stylesheet.</span>
</div>

<h1>DICT Free Wi-Fi — Survey Report</h1>
<p class="sub">
    @if ($activeFilters)
        Filters: {{ collect($activeFilters)->map(fn ($v, $k) => str_replace('_', ' ', $k).' = '.$v)->implode(' · ') }}<br>
    @else
        No filters applied — every site in your scope, all dates.<br>
    @endif
    Generated {{ $generatedAt->format('j M Y, H:i') }} by {{ $generatedBy }}.
    Contains only the data this account is permitted to see.
</p>

{{-- Summary --}}
<h2>Summary</h2>
<div class="tiles">
    @php
        $rated = (int) ($totals['rated'] ?? 0);
        $tiles = [
            ['Total responses', (string) ($totals['responses'] ?? 0), 'All responses in scope'],
            ['Average rating', $totals['average_rating'] ?? '—', $rated === 0 ? 'No rated responses yet' : 'From '.$rated.' rated response'.($rated === 1 ? '' : 's')],
            ['Positive', ($totals['positive_rate'] ?? null) === null ? '—' : $totals['positive_rate'].'%', 'Rating of '.$positiveThreshold.' or more'],
            ['Connection issue rate', ($totals['issue_rate'] ?? null) === null ? '—' : $totals['issue_rate'].'%', 'Share reporting a connection problem'],
            ['Verified portal', (string) ($totals['verified'] ?? 0), 'Confirmed by portal handoff'],
            ['Site link / QR', (string) ($totals['unverified'] ?? 0), 'Self-attributed, unverified'],
        ];
    @endphp

    @foreach ($tiles as $tile)
        <div class="tile">
            <div class="label">{{ $tile[0] }}</div>
            <div class="value">{{ $tile[1] }}</div>
            <div class="note">{{ $tile[2] }}</div>
        </div>
    @endforeach
</div>

<h2>By site</h2>
<table>
    <thead>
        <tr>
            <th>Site</th>
            <th>Location</th>
            <th class="num">Responses</th>
            <th class="num">Rated (n)</th>
            <th class="num">Avg rating</th>
            <th>Note</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($bySite as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['location'] ?? '—' }}</td>
                <td class="num">{{ $row['responses'] }}</td>
                <td class="num">{{ $row['rated'] }}</td>
                <td class="num">{{ $row['average_rating'] ?? '—' }}</td>
                <td>
                    @unless ($row['significant'])
                        <span class="flag">small sample (&lt; {{ $minimumSampleSize }})</span>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No responses for these filters.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="cols" style="margin-top:22px;">
    <div>
        <h2 style="margin-top:0;">By supplier</h2>
        <table>
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="num">Responses</th>
                    <th class="num">Avg (n)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bySupplier as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['responses'] }}</td>
                        <td class="num">{{ $row['average_rating'] ?? '—' }} ({{ $row['rated'] }})</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No responses.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <h2 style="margin-top:0;">Issues reported</h2>
        <table>
            <thead>
                <tr><th>Issue</th><th class="num">Count</th></tr>
            </thead>
            <tbody>
                @forelse ($byIssue as $row)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $row['issue_category'])) }}</td>
                        <td class="num">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">No responses.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Appendix: every individual response as one continuous table; CSS breaks the pages --}}
<h2>Appendix — individual responses</h2>

@if ($truncated)
    <p class="flag" style="display:block;padding:6px 8px;margin:0 0 8px;border-radius:2px;">
        This appendix lists the {{ number_format($responses->count()) }} most recent of
        {{ number_format($totalMatched) }} matching responses, capped so the document stays printable.
        {{ number_format($totalMatched - $responses->count()) }} older response(s) are not shown here —
        use the CSV export on the Feedback responses page for the complete set.
    </p>
@endif

<p class="small muted" style="margin:0 0 6px;">
    {{ number_format($totalMatched) }} response{{ $totalMatched === 1 ? '' : 's' }} matched, most recent first.
</p>

<table>
    <thead>
        <tr>
            <th>Reference</th>
            <th>Submitted</th>
            <th>Site</th>
            <th>Location</th>
            <th>Supplier</th>
            <th class="num">Ver</th>
            <th class="num">Rating</th>
            <th>Issue</th>
            <th>Attribution</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($responses as $response)
            <tr>
                <td class="small">{{ substr($response->id, 0, 8) }}</td>
                <td class="small">{{ $response->submitted_at?->format('j M Y H:i') }}</td>
                <td class="small">{{ $response->site?->site_code }}</td>
                <td class="small">{{ collect([$response->site?->municipality, $response->site?->province])->filter()->unique()->implode(', ') ?: '—' }}</td>
                <td class="small">{{ $response->supplier?->name }}</td>
                <td class="num small">{{ $response->surveyVersion?->version_number }}</td>
                <td class="num">{{ $response->overall_rating ?? '—' }}</td>
                <td class="small">{{ $response->issue_category ? ucfirst(str_replace('_', ' ', $response->issue_category)) : '—' }}</td>
                <td class="small">{{ $response->attribution_method->label() }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No responses matched these filters.</td></tr>
        @endforelse
    </tbody>
</table>

<p class="small muted" style="margin-top:18px;">
    <strong>How to read this:</strong> a positive response is a rating of {{ $positiveThreshold }} or more out of 5.
    Every average is shown with the number of rated responses (n) it came from, and groups with fewer than
    {{ $minimumSampleSize }} rated responses are flagged, because a handful of answers is not evidence of a trend.
    A figure shows &mdash; rather than 0% when there is no data to calculate it from.
    Verified portal and site link or QR responses are counted separately and never blended.
</p>

</body>
</html>
