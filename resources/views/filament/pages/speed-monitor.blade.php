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
            <li><strong>Download / upload</strong> are the sum of every connected device's current rate, in Kbps. They move with the traffic, so a busy site and an idle site look very different.</li>
            <li><strong>Clients</strong> counts every device the controller knows about. Wireless devices carry a speed; wired ones usually do not, so a site with many wired clients shows 0 Kbps even though it is busy.</li>
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
        $maxRx = max(1, max(array_column($snapshot ?: [['rx_rate' => 1]], 'rx_rate')));
    @endphp

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));margin-block:1.5rem;">
        @foreach ([
            ['label' => 'Locations', 'value' => count($snapshot), 'colour' => '#1d4ed8', 'fill' => '#eff6ff'],
            ['label' => 'Connected clients', 'value' => $totals['clients'], 'colour' => '#0891b2', 'fill' => '#ecfeff'],
            ['label' => 'Wireless', 'value' => $totals['wireless'], 'colour' => '#7c3aed', 'fill' => '#f5f3ff'],
            ['label' => 'Wired', 'value' => $totals['wired'], 'colour' => '#64748b', 'fill' => '#f1f5f9'],
            ['label' => 'Total download', 'value' => number_format($totals['rx']).' Kbps', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'Total upload', 'value' => number_format($totals['tx']).' Kbps', 'colour' => '#b45309', 'fill' => '#fffbeb'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.25);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.9rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.5rem;font-weight:700;line-height:1.1;color:{{ $tile['colour'] }};letter-spacing:-.02em;">{{ $tile['value'] }}</div>
            </div>
        @endforeach
    </div>

    <x-filament::section icon="heroicon-o-signal" heading="Throughput by location" description="Live, sorted by download speed.">
        <div style="overflow-x:auto;">
            <table class="fi-ta">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell">Site</th>
                        <th class="fi-ta-header-cell">Name</th>
                        <th class="fi-ta-header-cell">Province</th>
                        <th class="fi-ta-header-cell">Clients</th>
                        <th class="fi-ta-header-cell">Wireless</th>
                        <th class="fi-ta-header-cell">Wired</th>
                        <th class="fi-ta-header-cell">Download (Kbps)</th>
                        <th class="fi-ta-header-cell">Upload (Kbps)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshot as $row)
                        @php
                            $pct = round($row['rx_rate'] / $maxRx * 100, 1);
                        @endphp
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell">{{ $row['site_code'] }}</td>
                            <td class="fi-ta-cell">{{ $row['site_name'] }}</td>
                            <td class="fi-ta-cell">{{ $row['province'] ?? '—' }}</td>
                            <td class="fi-ta-cell">{{ $row['client_count'] }}</td>
                            <td class="fi-ta-cell">{{ $row['wireless_count'] }}</td>
                            <td class="fi-ta-cell">{{ $row['wired_count'] }}</td>
                            <td class="fi-ta-cell">
                                <span style="font-weight:600;">{{ number_format($row['rx_rate']) }}</span>
                                @if ($row['rx_rate'] > 0)
                                    <span style="display:inline-block;width:4rem;height:.5rem;background:rgba(128,128,128,.18);border-radius:999px;overflow:hidden;vertical-align:middle;margin-left:.4rem;">
                                        <span style="display:block;width:{{ $pct }}%;height:100%;border-radius:999px;background:#15803d;"></span>
                                    </span>
                                @endif
                            </td>
                            <td class="fi-ta-cell">{{ number_format($row['tx_rate']) }}</td>
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
                            <td class="fi-ta-cell" colspan="8">No live locations yet. A location shows up here once the controller has sent someone to it.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($refreshedAt)
            <p class="fi-section-header-description" style="margin-top:.75rem;">
                Last checked: {{ $refreshedAt }}. Cached for one minute; press Refresh for new numbers.
            </p>
        @endif
    </x-filament::section>

    @php
        $clientRows = [];
        foreach ($snapshot as $site) {
            foreach ($site['clients'] as $client) {
                if (! $client['wireless']) {
                    continue;
                }
                $clientRows[] = $site['site_code'].'|'.$client['mac'].'|'.$client['name'].'|'.$client['ssid'].'|'.$client['ap'].'|'.$client['rxRate'].'|'.$client['txRate'].'|'.$client['rssi'].'|'.$client['signal'];
            }
        }
    @endphp

    <x-filament::section icon="heroicon-o-device-phone-mobile" heading="Wireless devices right now" description="One row per connected device. Download and upload are the current rate in Kbps.">
        <div style="overflow-x:auto;">
            <table class="fi-ta">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell">Site</th>
                        <th class="fi-ta-header-cell">Device</th>
                        <th class="fi-ta-header-cell">SSID</th>
                        <th class="fi-ta-header-cell">Access point</th>
                        <th class="fi-ta-header-cell">Download (Kbps)</th>
                        <th class="fi-ta-header-cell">Upload (Kbps)</th>
                        <th class="fi-ta-header-cell">Signal (dBm)</th>
                        <th class="fi-ta-header-cell">Signal rank</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientRows as $row)
                        @php [$siteCode, $mac, $name, $ssid, $ap, $rx, $tx, $rssi, $signal] = explode('|', $row); @endphp
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell">{{ $siteCode }}</td>
                            <td class="fi-ta-cell" title="{{ $mac }}">{{ $name ?: $mac }}</td>
                            <td class="fi-ta-cell">{{ $ssid ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ $ap ?: '—' }}</td>
                            <td class="fi-ta-cell">{{ number_format((int) $rx) }}</td>
                            <td class="fi-ta-cell">{{ number_format((int) $tx) }}</td>
                            <td class="fi-ta-cell">{{ $rssi === '' ? '—' : $rssi }}</td>
                            <td class="fi-ta-cell">{{ $signal }}</td>
                        </tr>
                    @empty
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" colspan="8">No wireless devices connected right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="fi-section-header-description" style="margin-top:.75rem;">
            {{ count($clientRows) }} wireless device{{ count($clientRows) === 1 ? '' : 's' }}.
            Wired devices are listed in the location table above but do not carry a current rate from the controller.
        </p>
    </x-filament::section>
</x-filament-panels::page>
