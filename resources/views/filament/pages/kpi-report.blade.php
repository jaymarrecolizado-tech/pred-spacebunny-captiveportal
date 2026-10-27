{{--
    KPI report.

    Styling note: this view deliberately uses only Filament's `fi-*` components
    (section, badge, button, input, table) rather than generic Tailwind utility
    classes. The panel compiles no general utility set, so those classes are
    silently dropped and the page renders as bare, unstyled HTML. The only inline
    styles below are grid containers and the headline numerals, which have no
    Filament component equivalent.

    Rule: an average is never shown without the sample size behind it (FR-REP-003).
--}}
<x-filament-panels::page>
    <form method="GET">
        <x-filament::section
            icon="heroicon-o-funnel"
            heading="Filters"
            description="Narrow the figures below. Rates are calculated only from responses inside your scope."
        >
            <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));">
                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">From</span>
                    <x-filament::input.wrapper>
                        <input type="date" name="from" value="{{ $activeFilters['from'] ?? '' }}" class="fi-input">
                    </x-filament::input.wrapper>
                </label>

                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">To</span>
                    <x-filament::input.wrapper>
                        <input type="date" name="to" value="{{ $activeFilters['to'] ?? '' }}" class="fi-input">
                    </x-filament::input.wrapper>
                </label>

                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">Province</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="province">
                            <option value="">All provinces</option>
                            @foreach ($provinces as $value => $label)
                                <option value="{{ $value }}" @selected(($activeFilters['province'] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">Supplier</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="supplier_id">
                            <option value="">All suppliers</option>
                            @foreach ($suppliers as $id => $name)
                                <option value="{{ $id }}" @selected(($activeFilters['supplier_id'] ?? '') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">Site</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="site_id">
                            <option value="">All sites</option>
                            @foreach ($sites as $id => $code)
                                <option value="{{ $id }}" @selected(($activeFilters['site_id'] ?? '') === (string) $id)>{{ $code }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label style="display:block;">
                    <span class="fi-section-header-description" style="display:block;margin-bottom:.35rem;">Attribution</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select name="attribution">
                            <option value="">All sources</option>
                            @foreach ($attributions as $value => $label)
                                <option value="{{ $value }}" @selected(($activeFilters['attribution'] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>

            <x-slot name="footer">
                <x-filament::button type="submit">Apply filters</x-filament::button>
                <x-filament::button tag="a" :href="url()->current()" color="gray" outlined>Reset</x-filament::button>
                {{-- PDF carries the same filters and the same scope; see SurveyReportController. --}}
                <x-filament::button
                    tag="a"
                    :href="route('reports.survey', $activeFilters)"
                    color="gray"
                    outlined
                    icon="heroicon-o-document-arrow-down"
                >
                    Save as PDF
                </x-filament::button>
            </x-slot>        </x-filament::section>
    </form>

    {{-- Headline figures, colour coded. The colour is not decoration: a rating
         below 3 reads red and a positive rate reads green, so trouble is visible
         before the numbers are read. --}}
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));margin-block:1.5rem;">
        @php
            $rated = (int) ($totals['rated'] ?? 0);
            $avg = $totals['average_rating'] ?? null;
            $issueRate = $totals['issue_rate'] ?? null;

            // Colour follows the figure, not the label.
            $avgColour = $avg === null ? '#64748b' : ($avg >= 4 ? '#15803d' : ($avg >= 3 ? '#b45309' : '#b91c1c'));
            $avgFill = $avg === null ? '#f1f5f9' : ($avg >= 4 ? '#f0fdf4' : ($avg >= 3 ? '#fffbeb' : '#fef2f2'));

            $tiles = [
                ['label' => 'Total responses', 'value' => (int) ($totals['responses'] ?? 0), 'note' => 'All responses in scope for these filters.', 'colour' => '#1d4ed8', 'fill' => '#eff6ff'],
                ['label' => 'Average rating', 'value' => $avg, 'note' => $rated === 0 ? 'No rated responses yet.' : 'Out of 5, from '.$rated.' rated response'.($rated === 1 ? '' : 's').'.', 'colour' => $avgColour, 'fill' => $avgFill],
                ['label' => 'Positive responses', 'value' => ($totals['positive_rate'] ?? null) === null ? null : $totals['positive_rate'].'%', 'note' => 'Rating of '.$positiveThreshold.' or more.', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
                ['label' => 'Connection issue rate', 'value' => $issueRate === null ? null : $issueRate.'%', 'note' => $issueRate === null ? 'Nothing to report yet.' : ($issueRate > 20 ? 'High — worth investigating.' : ($issueRate > 0 ? 'Some connections are failing.' : 'No connection problems reported.')), 'colour' => $issueRate === null ? '#64748b' : ($issueRate > 20 ? '#b91c1c' : '#b45309'), 'fill' => $issueRate === null ? '#f1f5f9' : '#fffbeb'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div style="border:1px solid rgba(128,128,128,.25);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.9rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:2rem;font-weight:700;line-height:1.1;color:{{ $tile['colour'] }};letter-spacing:-.02em;">{{ $tile['value'] ?? '—' }}</div>
                <div style="font-size:.75rem;opacity:.7;margin-top:.3rem;">{{ $tile['note'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Charts. Drawn with CSS bars and an inline SVG ring: no chart library, no
         build step, and nothing extra to deploy to the server. --}}
    @php
        $siteRows = array_slice($bySite, 0, 10);
        $maxSiteResponses = max(1, max(array_column($siteRows ?: [['responses' => 1]], 'responses')));
        $palette = ['#2563eb', '#0891b2', '#7c3aed', '#c026d3', '#db2777', '#ea580c', '#ca8a04', '#65a30d', '#059669', '#0d9488'];
        $issueTotal = array_sum(array_column($byIssue, 'total'));
    @endphp

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));">
        {{-- Bar chart: responses per site. --}}
        <x-filament::section icon="heroicon-o-chart-bar" heading="Responses by location">
            @forelse ($siteRows as $i => $row)
                @php $pct = round($row['responses'] / $maxSiteResponses * 100, 1); @endphp
                <div style="display:grid;grid-template-columns:minmax(6rem,9rem) 1fr auto;align-items:center;gap:.6rem;margin-bottom:.45rem;">
                    <div style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                    <div style="background:rgba(128,128,128,.18);border-radius:999px;height:.85rem;overflow:hidden;">
                        <div style="width:{{ $pct }}%;height:100%;border-radius:999px;background:{{ $palette[$i % 10] }};"></div>
                    </div>
                    <div style="font-size:.78rem;font-weight:600;min-width:2.5rem;text-align:right;">{{ $row['responses'] }}</div>
                </div>
            @empty
                <p class="fi-section-header-description">No responses for these filters.</p>
            @endforelse

            @if (count($bySite) > 10)
                <p class="fi-section-header-description" style="margin-top:.5rem;">
                    Showing the 10 busiest of {{ count($bySite) }} locations. The full breakdown is in the tables and downloads below.
                </p>
            @endif
        </x-filament::section>

        {{-- Donut: what people reported. --}}
        <x-filament::section icon="heroicon-o-chart-pie" heading="What people reported">
            @if ($issueTotal > 0)
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:1.25rem;">
                    @php
                        $radius = 54;
                        $circumference = 2 * M_PI * $radius;
                        $offset = 0.0;
                    @endphp
                    <svg width="140" height="140" viewBox="0 0 140 140" role="img" aria-label="Share of responses by issue type">
                        <circle cx="70" cy="70" r="{{ $radius }}" fill="none" stroke="rgba(128,128,128,.15)" stroke-width="22"></circle>
                        @foreach ($byIssue as $i => $row)
                            @php
                                $share = $row['total'] / $issueTotal;
                                $length = $share * $circumference;
                            @endphp
                            <circle cx="70" cy="70" r="{{ $radius }}" fill="none"
                                stroke="{{ $palette[$i % 10] }}" stroke-width="22"
                                stroke-dasharray="{{ round($length, 2) }} {{ round($circumference - $length, 2) }}"
                                stroke-dashoffset="{{ round(-$offset, 2) }}"
                                transform="rotate(-90 70 70)"></circle>
                            @php $offset += $length; @endphp
                        @endforeach
                    </svg>

                    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.35rem;font-size:.8rem;">
                        @foreach ($byIssue as $i => $row)
                            <li style="display:flex;align-items:center;gap:.5rem;">
                                <span style="width:.7rem;height:.7rem;border-radius:2px;background:{{ $palette[$i % 10] }};flex:none;"></span>
                                <span>{{ ucfirst(str_replace('_', ' ', $row['issue_category'])) }}</span>
                                <span style="opacity:.7;">{{ $row['total'] }} ({{ round($row['total'] / $issueTotal * 100) }}%)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="fi-section-header-description">No responses for these filters.</p>
            @endif
        </x-filament::section>
    </div>

    {{-- Who answered. The point of the demographic questions: which groups are
         actually on the free Wi-Fi. "Did not answer" is shown as its own bar,
         because a chart that hides the people who skipped would read as if
         everybody had an opinion. --}}
    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));margin-block:1.5rem;">
        @foreach ([
            ['heading' => 'Who answered — gender', 'rows' => $byGender, 'colour' => '#2563eb'],
            ['heading' => 'Who answered — sector', 'rows' => $bySector, 'colour' => '#0891b2'],
        ] as $block)
            @php
                $maxRow = max(1, max(array_column($block['rows'] ?: [['total' => 1]], 'total')));
            @endphp
            <x-filament::section icon="heroicon-o-users" :heading="$block['heading']"
                description="Out of {{ $totals['responses'] ?? 0 }} response{{ ($totals['responses'] ?? 0) === 1 ? '' : 's' }}. Answers collected before these questions were added count as did not answer.">
                @forelse ($block['rows'] as $row)
                    @php
                        $pct = round($row['total'] / $maxRow * 100, 1);
                        $isUnanswered = $row['value'] === '';
                    @endphp
                    <div style="display:grid;grid-template-columns:minmax(7rem,10rem) 1fr auto;align-items:center;gap:.6rem;margin-bottom:.45rem;">
                        <div style="font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                        <div style="background:rgba(128,128,128,.18);border-radius:999px;height:.85rem;overflow:hidden;">
                            <div style="width:{{ $pct }}%;height:100%;border-radius:999px;background:{{ $isUnanswered ? 'rgba(128,128,128,.55)' : $block['colour'] }};"></div>
                        </div>
                        <div style="font-size:.78rem;font-weight:600;min-width:4.5rem;text-align:right;">
                            {{ $row['total'] }}
                            <span style="opacity:.65;font-weight:400;">{{ $row['share'] === null ? '' : $row['share'].'%' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="fi-section-header-description">No responses for these filters.</p>
                @endforelse
            </x-filament::section>
        @endforeach
    </div>

    {{-- Download buttons: the same report and the same filters, in four formats.
         Each renders the one ReportDocument, so the figures cannot disagree. --}}
    <x-filament::section icon="heroicon-o-arrow-down-tray" heading="Download this report" class="mt-6">
        <p class="fi-section-header-description">
            Every file leads with the summary, followed by the supporting data, and honours the filters above
            and your access scope.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;">
            @php
                $downloads = [
                    ['text' => 'PDF', 'route' => 'reports.survey', 'icon' => 'heroicon-o-document-text', 'hint' => 'Print or save as PDF', 'format' => null],
                    ['text' => 'Spreadsheet', 'route' => 'reports.survey.download', 'icon' => 'heroicon-o-table-cells', 'hint' => 'Two sheets: summary, then responses', 'format' => 'xlsx'],
                    ['text' => 'CSV', 'route' => 'reports.survey.download', 'icon' => 'heroicon-o-clipboard-document-list', 'hint' => 'Summary block, then the response rows', 'format' => 'csv'],
                    ['text' => 'XML', 'route' => 'reports.survey.download', 'icon' => 'heroicon-o-code-bracket', 'hint' => 'Structured, for another system to read', 'format' => 'xml'],
                ];
            @endphp

            @foreach ($downloads as $download)
                <x-filament::button
                    tag="a"
                    :href="route($download['route'], array_merge(
                        $activeFilters,
                        $download['format'] === null ? [] : ['format' => $download['format']],
                    ))"
                    color="gray"
                    outlined
                    size="sm"
                    :icon="$download['icon']"
                    :title="$download['hint']"
                >
                    {{ $download['text'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Verified vs unverified (FR-REP-006). --}}
    <x-filament::section icon="heroicon-o-shield-check" heading="Attribution" class="mb-6">
        <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));">
            <div>
                <x-filament::badge color="success" icon="heroicon-m-shield-check">Verified portal</x-filament::badge>
                <div style="margin-top:.5rem;font-size:1.5rem;font-weight:700;">{{ $totals['verified'] ?? 0 }}</div>
                <div class="fi-section-header-description">Confirmed by the captive portal handoff.</div>
            </div>
            <div>
                <x-filament::badge color="warning" icon="heroicon-m-link">Site link or QR</x-filament::badge>
                <div style="margin-top:.5rem;font-size:1.5rem;font-weight:700;">{{ $totals['unverified'] ?? 0 }}</div>
                <div class="fi-section-header-description">Self-attributed. Not tied to a MAC address.</div>
            </div>
            <div>
                <x-filament::badge color="info" icon="heroicon-m-device-phone-mobile">Distinct devices</x-filament::badge>
                <div style="margin-top:.5rem;font-size:1.5rem;font-weight:700;">{{ $totals['distinct_devices'] ?? 0 }}</div>
                <div class="fi-section-header-description">
                    {{ $totals['device_responses'] ?? 0 }} portal response{{ ($totals['device_responses'] ?? 0) === 1 ? '' : 's' }}.
                    Counted by hashed MAC, so this is devices, not people — one person
                    with a phone and a tablet counts twice. QR and site-link respondents
                    have no MAC and are not included.
                </div>
            </div>
        </div>
    </x-filament::section>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));">
        {{-- By site --}}
        <x-filament::section icon="heroicon-o-building-office-2" heading="By site" description="Ratings shown with their sample size.">
            <div style="overflow-x:auto;">
                <table class="fi-ta">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell">Site</th>
                            <th class="fi-ta-header-cell">Location</th>
                            <th class="fi-ta-header-cell">Responses</th>
                            <th class="fi-ta-header-cell">Share</th>
                            <th class="fi-ta-header-cell">Avg rating (n)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bySite as $row)
                            @php
                                $rowAvg = $row['average_rating'] ?? null;
                                $tint = $rowAvg === null ? '' : ($rowAvg >= 4 ? 'color:#15803d;' : ($rowAvg >= 3 ? 'color:#b45309;' : 'color:#b91c1c;'));
                                $rowShare = ($totals['responses'] ?? 0) === 0 ? null : round($row['responses'] / $totals['responses'] * 100, 1);
                            @endphp
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell">{{ $row['label'] }}</td>
                                <td class="fi-ta-cell">{{ $row['location'] ?? '—' }}</td>
                                <td class="fi-ta-cell">{{ $row['responses'] }}</td>
                                <td class="fi-ta-cell">{{ $rowShare === null ? '—' : $rowShare.'%' }}</td>
                                <td class="fi-ta-cell">
                                    <span style="font-weight:700;{{ $tint }}">{{ $row['average_rating'] ?? '—' }}</span>
                                    <span class="fi-section-header-description">(n={{ $row['rated'] }})</span>
                                    @unless ($row['significant'])
                                        <x-filament::badge color="warning" size="sm">small sample</x-filament::badge>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell" colspan="5">No responses for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- By supplier --}}
        <x-filament::section icon="heroicon-o-truck" heading="By supplier" description="Ratings shown with their sample size.">
            <div style="overflow-x:auto;">
                <table class="fi-ta">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell">Supplier</th>
                            <th class="fi-ta-header-cell">Responses</th>
                            <th class="fi-ta-header-cell">Avg rating (n)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bySupplier as $row)
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell">{{ $row['label'] }}</td>
                                <td class="fi-ta-cell">{{ $row['responses'] }}</td>
                                <td class="fi-ta-cell">
                                    <span style="font-weight:600;">{{ $row['average_rating'] ?? '—' }}</span>
                                    <span class="fi-section-header-description">(n={{ $row['rated'] }})</span>
                                    @unless ($row['significant'])
                                        <x-filament::badge color="warning" size="sm">small sample</x-filament::badge>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell" colspan="3">No responses for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));margin-block:1.5rem;">
        {{-- Issues --}}
        <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Issues reported">
            @forelse ($byIssue as $row)
                @php
                    // "No problem" is the good outcome, so it is not coloured
                    // like a fault.
                    $isProblem = $row['issue_category'] !== 'none';
                @endphp
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-block:.5rem;border-bottom:1px solid rgba(128,128,128,.18);">
                    <span style="display:flex;align-items:center;gap:.5rem;">
                        <span style="width:.65rem;height:.65rem;border-radius:999px;flex:none;background:{{ $isProblem ? '#dc2626' : '#16a34a' }};"></span>
                        {{ ucfirst(str_replace('_', ' ', $row['issue_category'])) }}
                    </span>
                    <x-filament::badge :color="$isProblem ? 'danger' : 'success'">{{ $row['total'] }}</x-filament::badge>
                </div>
            @empty
                <p class="fi-section-header-description">No responses for these filters.</p>
            @endforelse
        </x-filament::section>

        {{-- Comments by location. People often write in where the Wi-Fi is
             worst, so the count per site matters more than the flat list. --}}
        <x-filament::section icon="heroicon-o-chat-bubble-left-ellipsis" heading="Comments by location" description="Written answers, grouped by site.">
            <div style="overflow-x:auto;">
                <table class="fi-ta">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell">Site</th>
                            <th class="fi-ta-header-cell">Location</th>
                            <th class="fi-ta-header-cell">Comments</th>
                            <th class="fi-ta-header-cell">Share of all responses</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commentsBySite as $row)
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell">{{ $row['site_code'] }}</td>
                                <td class="fi-ta-cell">{{ $row['site_name'] }}</td>
                                <td class="fi-ta-cell">{{ $row['total'] }}</td>
                                <td class="fi-ta-cell">{{ $row['share'] === null ? '—' : $row['share'].'%' }}</td>
                            </tr>
                        @empty
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell" colspan="4">Nobody left a written comment for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (count($commentsBySite) > 0)
                <p class="fi-section-header-description" style="margin-top:.5rem;">
                    {{ array_sum(array_column($commentsBySite, 'total')) }} written comment{{ array_sum(array_column($commentsBySite, 'total')) === 1 ? '' : 's' }} in total.
                </p>
            @endif
        </x-filament::section>

        {{-- Daily --}}
        <x-filament::section icon="heroicon-o-calendar-days" heading="Daily responses" description="Most recent first.">
            @forelse (array_reverse($daily) as $row)
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-block:.5rem;border-bottom:1px solid var(--fi-color-gray-200,rgba(128,128,128,.2));">
                    <span>{{ $row['day'] }}</span>
                    <span>
                        <span style="font-weight:600;">{{ $row['responses'] }}</span>
                        <span class="fi-section-header-description">avg {{ $row['average_rating'] ?? '—' }}</span>
                    </span>
                </div>
            @empty
                <p class="fi-section-header-description">No responses for these filters.</p>
            @endforelse
        </x-filament::section>
    </div>

    <x-filament::section icon="heroicon-o-information-circle" heading="How these figures are calculated">
        <ul style="margin:0;padding-inline-start:1.25rem;display:grid;gap:.35rem;">
            <li><strong>Positive</strong> means a rating of {{ $positiveThreshold }} or more out of 5.</li>
            <li><strong>Averages always carry (n)</strong>, the number of rated responses they came from.</li>
            <li>Groups with fewer than {{ $minimumSampleSize }} rated responses are flagged
                <strong>small sample</strong>, because a handful of answers is not evidence of a trend.</li>
            <li>A figure shows <strong>—</strong> rather than 0% when there is no data to calculate it from.</li>
            <li>Verified portal and site link or QR responses are reported separately and never blended.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
