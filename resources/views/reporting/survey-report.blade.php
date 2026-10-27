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

        /* Chart container. Kept white in print so the colours in the SVG and
           bars stay readable regardless of the browser's dark mode. */
        .chart-card {
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 10px 12px;
            background: #fff;
        }

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

            /* Page 1 is the summary on its own; the supporting data starts on a
               fresh page behind it, so the headline figures are never split from
               their heading or stranded at the bottom of a page. */
            .summary-block { page-break-after: always; break-after: page; }
            .summary-block:last-of-type { page-break-after: auto; break-after: auto; }
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

{{-- Summary. Class marks the end of page 1: the supporting data always starts
     on a fresh page behind it, in every output format. --}}
<div class="summary-block">
    <h2 style="margin-top:0;">Summary</h2>
    <div class="tiles">
    {{-- The same summary lines the CSV, spreadsheet and XML print. Rendering
         them from here is what stops the PDF quietly disagreeing with a
         download of the same report (FR-REP-005). --}}
    @foreach ($summary as $tile)
        <div class="tile">
            <div class="label">{{ $tile['label'] }}</div>
            <div class="value">{{ $tile['value'] ?? '—' }}</div>
            <div class="note">{{ $tile['note'] }}</div>
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

{{-- Charts on the summary page. Inline SVG and CSS bars only: no chart library,
     nothing to install, and vector shapes that stay crisp in the saved PDF. --}}
@php
    $palette = ['#2563eb', '#0891b2', '#7c3aed', '#c026d3', '#db2777', '#ea580c', '#ca8a04', '#65a30d', '#059669', '#0d9488'];
    $ratingRows = $ratingDistribution ?? [];
    $ratingTotal = array_sum(array_column($ratingRows, 'total'));
    $maxRating = max(1, max(array_column($ratingRows, 'total') ?: [1]));
    $issueTotalAll = array_sum(array_column($byIssue, 'total'));
    $topSites = array_slice($bySite, 0, 8);
    $maxSite = max(1, max(array_column($topSites ?: [['responses' => 1]], 'responses')));
@endphp

<h2>Rating breakdown</h2>
<div class="chart-card">
    @if ($ratingTotal > 0)
        <svg viewBox="0 0 300 120" width="100%" height="120" role="img" aria-label="How many people gave each star rating">
            @foreach ($ratingRows as $i => $row)
                @php
                    // 5 stars tallest, 1 star shortest. Green at the top, red at
                    // the bottom, so the shape reads as opinion at a glance.
                    $colour = $row['stars'] >= 4 ? '#15803d' : ($row['stars'] === 3 ? '#b45309' : '#b91c1c');
                    $h = $row['total'] > 0 ? max(3, round($row['total'] / $maxRating * 88)) : 1;
                    $x = 22 + $i * 58;
                @endphp
                <rect x="{{ $x }}" y="{{ 100 - $h }}" width="34" height="{{ $h }}"
                      fill="{{ $row['total'] > 0 ? $colour : 'rgba(128,128,128,.2)' }}" rx="2"></rect>
                <text x="{{ $x + 17 }}" y="112" text-anchor="middle" font-size="10" fill="#6b7280">{{ $row['stars'] }}★</text>
                <text x="{{ $x + 17 }}" y="{{ 96 - $h }}" text-anchor="middle" font-size="10" font-weight="bold" fill="#111827">
                    {{ $row['total'] > 0 ? $row['total'] : '' }}
                </text>
            @endforeach
        </svg>
        <p class="muted" style="font-size:.75rem;margin-top:.4rem;">
            {{ $ratingTotal }} rated response{{ $ratingTotal === 1 ? '' : 's' }}.
            Average {{ $totals['average_rating'] ?? '—' }} out of 5.
        </p>
    @else
        <p class="muted" style="font-size:.8rem;">No rated responses for these filters.</p>
    @endif
</div>

<div class="cols" style="margin-top:22px;">
    <div>
        <h2 style="margin-top:0;">Responses by location</h2>
        <div class="chart-card">
            @forelse ($topSites as $i => $row)
                @php $width = round($row['responses'] / $maxSite * 100, 1); @endphp
                <div style="display:grid;grid-template-columns:minmax(5rem,7rem) 1fr auto;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                    <span style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $row['label'] }}</span>
                    <span style="background:rgba(128,128,128,.18);border-radius:999px;height:.6rem;overflow:hidden;display:block;">
                        <span style="display:block;width:{{ $width }}%;height:100%;border-radius:999px;background:{{ $palette[$i % 10] }};"></span>
                    </span>
                    <span style="font-size:.72rem;font-weight:bold;">{{ $row['responses'] }}</span>
                </div>
            @empty
                <p class="muted" style="font-size:.8rem;">No responses for these filters.</p>
            @endforelse
        </div>
    </div>

    <div>
        <h2 style="margin-top:0;">What people reported</h2>
        <div class="chart-card" style="display:flex;align-items:center;gap:1rem;">
            @if ($issueTotalAll > 0)
                @php
                    $radius = 42;
                    $circumference = 2 * M_PI * $radius;
                    $offset = 0.0;
                @endphp
                <svg width="108" height="108" viewBox="0 0 108 108" style="flex:none;" role="img" aria-label="Share of responses by issue type">
                    <circle cx="54" cy="54" r="{{ $radius }}" fill="none" stroke="rgba(128,128,128,.15)" stroke-width="18"></circle>
                    @foreach ($byIssue as $i => $row)
                        @php
                            $share = $row['total'] / $issueTotalAll;
                            $length = $share * $circumference;
                        @endphp
                        <circle cx="54" cy="54" r="{{ $radius }}" fill="none"
                            stroke="{{ $palette[$i % 10] }}" stroke-width="18"
                            stroke-dasharray="{{ round($length, 2) }} {{ round($circumference - $length, 2) }}"
                            stroke-dashoffset="{{ round(-$offset, 2) }}"
                            transform="rotate(-90 54 54)"></circle>
                        @php $offset += $length; @endphp
                    @endforeach
                </svg>
                <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.25rem;font-size:.72rem;">
                    @foreach ($byIssue as $i => $row)
                        <li style="display:flex;align-items:center;gap:.4rem;">
                            <span style="width:.55rem;height:.55rem;border-radius:2px;background:{{ $palette[$i % 10] }};flex:none;"></span>
                            <span>{{ ucfirst(str_replace('_', ' ', $row['issue_category'])) }}</span>
                            <span class="muted">{{ $row['total'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted" style="font-size:.8rem;">No responses for these filters.</p>
            @endif
        </div>
    </div>
</div>

<div class="cols" style="margin-top:22px;">
    <div>
        <h2 style="margin-top:0;">Who answered</h2>
        <p class="muted" style="font-size:.75rem;margin:0 0 .5rem;">
            Out of {{ $totals['responses'] ?? 0 }} response{{ ($totals['responses'] ?? 0) === 1 ? '' : 's' }}.
            Answers collected before these questions were added count as did not answer.
        </p>

        @foreach ([
            ['heading' => 'Gender', 'rows' => $byGender, 'colour' => $palette[0]],
            ['heading' => 'Sector', 'rows' => $bySector, 'colour' => $palette[1]],
        ] as $block)
            @php
                $maxRow = max(1, max(array_column($block['rows'] ?: [['total' => 1]], 'total')));
            @endphp
            <h3 style="font-size:.8rem;margin:.7rem 0 .35rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">{{ $block['heading'] }}</h3>
            <div class="chart-card">
                @forelse ($block['rows'] as $row)
                    @php
                        $width = round($row['total'] / $maxRow * 100, 1);
                        $isUnanswered = $row['value'] === '';
                    @endphp
                    <div style="display:grid;grid-template-columns:minmax(5rem,7rem) 1fr auto;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                        <span style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                        <span style="background:rgba(128,128,128,.18);border-radius:999px;height:.6rem;overflow:hidden;display:block;">
                            <span style="display:block;width:{{ $width }}%;height:100%;border-radius:999px;background:{{ $isUnanswered ? 'rgba(128,128,128,.55)' : $block['colour'] }};"></span>
                        </span>
                        <span style="font-size:.72rem;font-weight:bold;">{{ $row['total'] }}</span>
                    </div>
                @empty
                    <p class="muted" style="font-size:.8rem;">No responses for these filters.</p>
                @endforelse
            </div>
        @endforeach
    </div>

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
</div>{{-- /summary-block: page 1 ends here --}}

{{-- Comments grouped by location, so the reader can see where the words came
     from without scanning the whole table. --}}
<h2>Comments by location</h2>
<div class="chart-card">
    @if (count($commentsBySite) > 0)
        <table>
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Location</th>
                    <th class="num">Comments</th>
                    <th class="num">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($commentsBySite as $row)
                    <tr>
                        <td>{{ $row['site_code'] }}</td>
                        <td>{{ $row['site_name'] }}</td>
                        <td class="num">{{ $row['total'] }}</td>
                        <td class="num">{{ $row['share'] === null ? '—' : $row['share'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="font-size:.75rem;margin-top:.5rem;">
            {{ array_sum(array_column($commentsBySite, 'total')) }} written comment{{ array_sum(array_column($commentsBySite, 'total')) === 1 ? '' : 's' }} in total.
        </p>
    @else
        <p class="muted" style="font-size:.8rem;">Nobody left a written comment for these filters.</p>
    @endif
</div>

{{-- What people actually wrote. Before the response table, because a reader
     usually wants the words first and the rows second. --}}
<h2>What people wrote</h2>

@if (count($comments) > 0)
    <p class="muted" style="font-size:.75rem;margin:0 0 .6rem;">
        {{ count($comments) }} written comment{{ count($comments) === 1 ? '' : 's' }}.
        These are the public's own words and have not been edited. Treat this page as sensitive.
    </p>

    @foreach ($comments as $comment)
        <div style="border-left:3px solid var(--line);padding:.35rem .7rem;margin-bottom:.6rem;page-break-inside:avoid;">
            <div style="font-size:.72rem;color:var(--muted);margin-bottom:.15rem;">
                {{ $comment['site_code'] }} &middot; {{ $comment['submitted_at'] }}
                @if ($comment['rating'] !== '') &middot; {{ $comment['rating'] }}&star; @endif
            </div>
            @if ($comment['question'] !== '')
                <div style="font-size:.72rem;font-weight:600;margin-bottom:.15rem;">{{ $comment['question'] }}</div>
            @endif
            <div style="font-size:.85rem;">{{ $comment['comment'] }}</div>
        </div>
    @endforeach
@else
    <p class="muted" style="font-size:.8rem;">Nobody left a written comment for these filters.</p>
@endif

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

<p class="small muted">
    <strong>About the distinct device count:</strong> it is the number of different network devices
    (by hashed MAC address) that submitted a survey through the captive portal, not the number of people.
    One person carrying a phone and a tablet counts as two, and a connection that drops and re-associates
    counts again. It covers only portal submissions: a person answering through a QR code or site link
    presents no device address and is therefore not included. No raw MAC address is stored — responses hold
    a SHA-256 hash, which is enough to count repeats and not enough to identify a device.
</p>

</body>
</html>
