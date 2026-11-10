{{--
    Wi-Fi link rates: the snapshot summary and a plain explanation.

    These figures are current wireless link rates reported by the controller,
    not measured traffic used. AP -> Device is the controller transmitting to a
    client; Device -> AP is the reverse.

    Only Filament components and inline styles here, because the panel ships
    no general utility set. The two tables below this section are real Filament
    table widgets (see getFooterWidgets()).
--}}
<x-filament-panels::page>
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));">
        @foreach ([
            ['label' => 'Locations', 'value' => $locations, 'note' => $reachable === $locations ? 'All reachable' : ($reachable.' of '.$locations.' reachable'), 'colour' => '#144666', 'fill' => '#eef6fb'],
            ['label' => 'Connected clients', 'value' => $clients, 'note' => 'Devices the controller can see', 'colour' => '#1f5f82', 'fill' => '#f3f7fb'],
            ['label' => 'Wireless', 'value' => $wireless, 'note' => 'Wireless link rates shown', 'colour' => '#d9a437', 'fill' => '#f7f5ee'],
            ['label' => 'Wired', 'value' => $wired, 'note' => 'No per-client rate reported', 'colour' => '#64748b', 'fill' => '#f1f5f9'],
            ['label' => 'Peak AP → Device', 'value' => number_format($peakApToClientMbps, 1).' Mbps', 'note' => 'Best negotiated wireless link rate', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'Basic/low-rate devices', 'value' => $lowRateDevices, 'note' => 'Wireless clients at 12 Mbps or below', 'colour' => '#b45309', 'fill' => '#fffbeb'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.22);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.85rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.45rem;font-weight:700;line-height:1.15;color:{{ $tile['colour'] }};letter-spacing:-.02em;font-variant-numeric:tabular-nums;">{{ $tile['value'] }}</div>
                <div style="font-size:.72rem;opacity:.7;margin-top:.25rem;">{{ $tile['note'] }}</div>
            </div>
        @endforeach
    </div>

    <x-filament::section
        icon="heroicon-o-chart-bar-square"
        heading="Actual traffic, last hour"
        description="Measured from timed samples of cumulative traffic counters, not from current radio rates."
        style="margin-top:1.25rem;"
    >
        @php
            $actualDownload = $actualDownMb >= 1024
                ? number_format($actualDownMb / 1024, 1).' GB'
                : number_format($actualDownMb, 1).' MB';
            $actualUpload = $actualUpMb >= 1024
                ? number_format($actualUpMb / 1024, 1).' GB'
                : number_format($actualUpMb, 1).' MB';
            $average = $actualDownMbps === null
                ? '—'
                : number_format($actualDownMbps, 1).' Mbps';
        @endphp

        <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));margin-bottom:1rem;">
            @foreach ([
                ['label' => 'Downloaded', 'value' => $actualDownload],
                ['label' => 'Uploaded', 'value' => $actualUpload],
                ['label' => 'Avg down per sampled site', 'value' => $average],
                ['label' => 'Samples stored', 'value' => $sampledSites === 0 ? '0' : ($sampledSites.' sites')],
            ] as $tile)
                <div style="border:1px solid rgba(128,128,128,.22);border-radius:.6rem;padding:.85rem 1rem;">
                    <div style="font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                    <div style="font-size:1.2rem;font-weight:700;line-height:1.2;color:#144666;font-variant-numeric:tabular-nums;">{{ $tile['value'] }}</div>
                </div>
            @endforeach
        </div>

        <p class="fi-section-header-description">
            Last sample: {{ $lastSampleAt }}. Samples are collected by the server scheduler every five minutes.
            Clients present in both samples are matched by a keyed MAC hash; new, missing, or counter-reset clients are excluded rather than counted as traffic.
        </p>
    </x-filament::section>

    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="How to read this page"
        description="Taken at {{ $refreshedAt }}, from the Wi-Fi controller. Refresh for a new snapshot."
        style="margin-top:1.25rem;"
    >
        <ul style="margin:0;padding-inline-start:1.25rem;display:grid;gap:.4rem;font-size:.85rem;line-height:1.5;">
            <li><strong>These are link rates, not actual traffic.</strong> The controller reports the radio rate currently negotiated with each client, in Kbps. It is not a speed-test result, not bytes transferred, and not proof of internet capacity.</li>
            <li><strong>Peak means the highest negotiated radio rate.</strong> AP → Device is the best link to a client; Device → AP is the best link back. Basic/low-rate devices are wireless clients at 12 Mbps or below. None of these figures is measured internet throughput.</li>
            <li><strong>Wired devices have no wireless rate.</strong> The controller does not report a per-client wireless radio rate for them, so they are counted but do not appear as wireless link-rate rows.</li>
            <li><strong>Link rates are not traffic.</strong> The upper tables show the radio rate each client has negotiated right now. The “Actual traffic” section is measured from timed counter samples and is the only throughput figure on this page.</li>
            <li><strong>First samples do not produce Mbps.</strong> The first sample establishes a baseline; later samples can calculate bytes transferred. A new or resetting client is not invented as traffic.</li>
            <li><strong>Refresh</strong> clears the one-minute cache, so pressing it twice within a minute is the same as pressing it once.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
