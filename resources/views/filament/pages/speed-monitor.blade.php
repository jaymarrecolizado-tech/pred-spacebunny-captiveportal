{{--
    Speed monitor: a live snapshot of throughput per location.

    Same styling rule as the KPI page: only Filament components and inline
    styles, because the panel compiles no general utility set.
--}}
<x-filament-panels::page>
    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="How this is measured"
        description="Point-in-time only. The controller keeps no history of speed, so this page is a snapshot taken when you load it."
    >
        <ul style="margin:0;padding-inline-start:1.25rem;display:grid;gap:.35rem;">
            <li><strong>Download / upload</strong> are the sum of every connected device's current rate, in Kbps, converted to Mbps. They move with the traffic, so a busy site and an idle site look very different.</li>
            <li><strong>Clients</strong> counts every device the controller knows about. Wireless devices carry a speed; wired ones usually do not, so a site with many wired clients shows 0 Mbps even though it is busy.</li>
            <li><strong>Refresh</strong> fetches new numbers. Results are cached for one minute so the page does not hammer the controller.</li>
            <li>To get <strong>trends</strong> rather than snapshots, poll this on a schedule and store the results. That is the next step if this page proves useful.</li>
        </ul>
    </x-filament::section>

    @php
        $totals = [
            'clients' => array_sum(array_column($snapshot, 'client_count')),
            'wireless' => array_sum(array_column($snapshot, 'wireless_count')),
            'wired' => array_sum(array_column($snapshot, 'wired_count')),
            'rx' => array_sum(array_column($snapshot, 'rx_rate')),
            'tx' => array_sum(array_column($snapshot, 'tx_rate')),
        ];
    @endphp

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));margin-block:1.5rem;">
        @foreach ([
            ['label' => 'Locations', 'value' => count($snapshot), 'colour' => '#1d4ed8', 'fill' => '#eff6ff'],
            ['label' => 'Connected clients', 'value' => $totals['clients'], 'colour' => '#0891b2', 'fill' => '#ecfeff'],
            ['label' => 'Wireless', 'value' => $totals['wireless'], 'colour' => '#7c3aed', 'fill' => '#f5f3ff'],
            ['label' => 'Wired', 'value' => $totals['wired'], 'colour' => '#64748b', 'fill' => '#f1f5f9'],
            ['label' => 'Total download', 'value' => number_format(round($totals['rx'] / 1000, 1)).' Mbps', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'Total upload', 'value' => number_format(round($totals['tx'] / 1000, 1)).' Mbps', 'colour' => '#b45309', 'fill' => '#fffbeb'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.25);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.9rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.5rem;font-weight:700;line-height:1.1;color:{{ $tile['colour'] }};letter-spacing:-.02em;">{{ $tile['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Per-site table with sort, filter, pagination --}}
    <x-filament::section icon="heroicon-o-signal" heading="Throughput by location" description="Live, sorted by download speed.">
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;align-items:center;">
            <x-filament::input.wrapper style="flex:1;min-width:12rem;">
                <input type="search" wire:model.live.debounce.300ms="siteSearch" placeholder="Search site code or name..." class="fi-input">
            </x-filament::input.wrapper>
            <x-filament::input.wrapper style="min-width:10rem;">
                <x-filament::input.select wire:model.live="siteProvinceFilter">
                    <option value="">All provinces</option>
                    @foreach ($provinces as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::input.wrapper style="min-width:6rem;">
                <x-filament::input.select wire:model.live="sitePerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div style="overflow-x:auto;">
            <table class="fi-ta">
                <thead>
                    <tr>
                        @foreach ([
                            ['col' => 'site_code', 'label' => 'Site'],
                            ['col' => 'site_name', 'label' => 'Name'],
                            ['col' => 'province', 'label' => 'Province'],
                            ['col' => 'client_count', 'label' => 'Clients'],
                            ['col' => 'wireless_count', 'label' => 'Wireless'],
                            ['col' => 'wired_count', 'label' => 'Wired'],
                            ['col' => 'rx_rate', 'label' => 'Download (Mbps)'],
                            ['col' => 'tx_rate', 'label' => 'Upload (Mbps)'],
                        ] as $h)
                            <th class="fi-ta-header-cell" style="cursor:pointer;user-select:none;" wire:click="sortSite('{{ $h['col'] }}')">
                                {{ $h['label'] }}
                                @if ($siteSortColumn === $h['col'])
                                    <span style="opacity:.6;">{{ $siteSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($siteRows as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" style="font-weight:600;">{{ $row['site_code'] }}</td>
                            <td class="fi-ta-cell">{{ $row['site_name'] }}</td>
                            <td class="fi-ta-cell">{{ $row['province'] ?? '—' }}</td>
                            <td class="fi-ta-cell">{{ $row['client_count'] }}</td>
                            <td class="fi-ta-cell">{{ $row['wireless_count'] }}</td>
                            <td class="fi-ta-cell">{{ $row['wired_count'] }}</td>
                            <td class="fi-ta-cell" style="font-weight:600;">{{ number_format(round($row['rx_rate'] / 1000, 1), 1) }}</td>
                            <td class="fi-ta-cell">{{ number_format(round($row['tx_rate'] / 1000, 1), 1) }}</td>
                        </tr>
                        @if (! empty($row['error']))
                            <tr class="fi-ta-row">
                                <td class="fi-ta-cell" colspan="8" style="color:#b91c1c;font-size:.78rem;">
                                    Could not read this location: {{ $row['error'] }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" colspan="8">No locations match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php
            $siteTotalPages = max(1, ceil($siteTotal / $sitePerPage));
        @endphp
        @if ($siteTotalPages > 1)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.75rem;flex-wrap:wrap;gap:.5rem;">
                <span class="fi-section-header-description">
                    {{ $siteTotal }} location{{ $siteTotal === 1 ? '' : 's' }}. Page {{ $sitePage }} of {{ $siteTotalPages }}.
                </span>
                <div style="display:flex;gap:.25rem;">
                    <x-filament::button size="sm" color="gray" outlined :disabled="$sitePage <= 1" wire:click="prevSitePage">Prev</x-filament::button>
                    <x-filament::button size="sm" color="gray" outlined :disabled="$sitePage >= $siteTotalPages" wire:click="nextSitePage">Next</x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- Per-client table with sort, filter, pagination --}}
    <x-filament::section icon="heroicon-o-device-phone-mobile" heading="Wireless devices right now" description="One row per connected device. Speeds in Mbps.">
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem;align-items:center;">
            <x-filament::input.wrapper style="flex:1;min-width:12rem;">
                <input type="search" wire:model.live.debounce.300ms="clientSearch" placeholder="Search device, SSID, or site..." class="fi-input">
            </x-filament::input.wrapper>
            <x-filament::input.wrapper style="min-width:12rem;">
                <x-filament::input.select wire:model.live="clientSiteFilter">
                    <option value="">All sites</option>
                    @foreach ($siteCodes as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::input.wrapper style="min-width:6rem;">
                <x-filament::input.select wire:model.live="clientPerPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div style="overflow-x:auto;">
            <table class="fi-ta">
                <thead>
                    <tr>
                        @foreach ([
                            ['col' => 'site_code', 'label' => 'Site'],
                            ['col' => 'name', 'label' => 'Device'],
                            ['col' => 'ssid', 'label' => 'SSID'],
                            ['col' => 'ap', 'label' => 'Access point'],
                            ['col' => 'rxRate', 'label' => 'Download (Mbps)'],
                            ['col' => 'txRate', 'label' => 'Upload (Mbps)'],
                            ['col' => 'rssi', 'label' => 'Signal (dBm)'],
                            ['col' => 'signal', 'label' => 'Signal rank'],
                        ] as $h)
                            <th class="fi-ta-header-cell" style="cursor:pointer;user-select:none;" wire:click="sortClient('{{ $h['col'] }}')">
                                {{ $h['label'] }}
                                @if ($clientSortColumn === $h['col'])
                                    <span style="opacity:.6;">{{ $clientSortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientRows as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" style="font-weight:600;">{{ $row['site_code'] }}</td>
                            <td class="fi-ta-cell" title="{{ $row['mac'] }}">{{ $row['name'] ?: $row['mac'] }}</td>
                            <td class="fi-ta-cell">{{ $row['ssid'] ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ $row['ap'] ?: '—' }}</td>
                            <td class="fi-ta-cell" style="font-weight:600;">{{ number_format(round($row['rxRate'] / 1000, 1), 1) }}</td>
                            <td class="fi-ta-cell">{{ number_format(round($row['txRate'] / 1000, 1), 1) }}</td>
                            <td class="fi-ta-cell">{{ $row['rssi'] === null ? '—' : $row['rssi'] }}</td>
                            <td class="fi-ta-cell">{{ $row['signal'] }}</td>
                        </tr>
                    @empty
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" colspan="8">No wireless devices match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @php
            $clientTotalPages = max(1, ceil($clientTotal / $clientPerPage));
        @endphp
        @if ($clientTotalPages > 1)
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.75rem;flex-wrap:wrap;gap:.5rem;">
                <span class="fi-section-header-description">
                    {{ $clientTotal }} device{{ $clientTotal === 1 ? '' : 's' }}. Page {{ $clientPage }} of {{ $clientTotalPages }}.
                </span>
                <div style="display:flex;gap:.25rem;">
                    <x-filament::button size="sm" color="gray" outlined :disabled="$clientPage <= 1" wire:click="prevClientPage">Prev</x-filament::button>
                    <x-filament::button size="sm" color="gray" outlined :disabled="$clientPage >= $clientTotalPages" wire:click="nextClientPage">Next</x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
