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
            </x-slot>
        </x-filament::section>
    </form>

    {{-- Headline figures. --}}
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));margin-block:1.5rem;">
        @php
            $rated = (int) ($totals['rated'] ?? 0);
            $tiles = [
                ['label' => 'Total responses', 'value' => (int) ($totals['responses'] ?? 0), 'note' => 'All responses in scope for these filters.'],
                ['label' => 'Average rating', 'value' => $totals['average_rating'] ?? null, 'note' => $rated === 0 ? 'No rated responses yet.' : 'From '.$rated.' rated response'.($rated === 1 ? '' : 's').'.'],
                ['label' => 'Positive responses', 'value' => ($totals['positive_rate'] ?? null) === null ? null : $totals['positive_rate'].'%', 'note' => 'Rating of '.$positiveThreshold.' or more, from '.$rated.' rated response'.($rated === 1 ? '' : 's').'.'],
                ['label' => 'Connection issue rate', 'value' => ($totals['issue_rate'] ?? null) === null ? null : $totals['issue_rate'].'%', 'note' => 'Share of all responses reporting a connection problem.'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <x-filament::section compact :heading="$tile['label']">
                <div style="font-size:1.875rem;font-weight:700;line-height:1.1;letter-spacing:-.02em;">
                    {{ $tile['value'] ?? '—' }}
                </div>
                <div class="fi-section-header-description" style="margin-top:.35rem;">
                    {{ $tile['note'] }}
                </div>
            </x-filament::section>
        @endforeach
    </div>

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
                            <th class="fi-ta-header-cell">Responses</th>
                            <th class="fi-ta-header-cell">Avg rating (n)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bySite as $row)
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
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-block:.5rem;border-bottom:1px solid var(--fi-color-gray-200,rgba(128,128,128,.2));">
                    <span>{{ ucfirst(str_replace('_', ' ', $row['issue_category'])) }}</span>
                    <x-filament::badge color="gray">{{ $row['total'] }}</x-filament::badge>
                </div>
            @empty
                <p class="fi-section-header-description">No responses for these filters.</p>
            @endforelse
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
