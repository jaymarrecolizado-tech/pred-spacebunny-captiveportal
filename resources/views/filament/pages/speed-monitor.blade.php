{{--
    Speed monitor: the snapshot summary, plus a plain explanation.

    Only Filament components and inline styles here, because the panel ships
    no general utility set. The two tables below this section are real Filament
    table widgets (see getFooterWidgets()).
--}}
<x-filament-panels::page>
    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));">
        @foreach ([
            ['label' => 'Locations', 'value' => $locations, 'note' => $reachable === $locations ? 'All reachable' : ($reachable.' of '.$locations.' reachable'), 'colour' => '#1d4ed8', 'fill' => '#eff6ff'],
            ['label' => 'Connected clients', 'value' => $clients, 'note' => 'Devices the controller can see', 'colour' => '#0891b2', 'fill' => '#ecfeff'],
            ['label' => 'Wireless', 'value' => $wireless, 'note' => 'Carry a current speed', 'colour' => '#7c3aed', 'fill' => '#f5f3ff'],
            ['label' => 'Wired', 'value' => $wired, 'note' => 'No per-client rate reported', 'colour' => '#64748b', 'fill' => '#f1f5f9'],
            ['label' => 'Download now', 'value' => number_format($downloadMbps, 1).' Mbps', 'note' => 'Sum of every wireless device', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'Upload now', 'value' => number_format($uploadMbps, 1).' Mbps', 'note' => 'Sum of every wireless device', 'colour' => '#b45309', 'fill' => '#fffbeb'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.22);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.85rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.45rem;font-weight:700;line-height:1.15;color:{{ $tile['colour'] }};letter-spacing:-.02em;font-variant-numeric:tabular-nums;">{{ $tile['value'] }}</div>
                <div style="font-size:.72rem;opacity:.7;margin-top:.25rem;">{{ $tile['note'] }}</div>
            </div>
        @endforeach
    </div>

    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="How to read this page"
        description="Taken at {{ $refreshedAt }}, from the Wi-Fi controller. Refresh for a new snapshot."
        style="margin-top:1.25rem;"
    >
        <ul style="margin:0;padding-inline-start:1.25rem;display:grid;gap:.4rem;font-size:.85rem;line-height:1.5;">
            <li><strong>Speed is per device.</strong> Download and upload add up every connected device's current rate, then show it in Mbps. A busy location looks fast; an idle one looks slow even when the link is fine.</li>
            <li><strong>Wired devices have no rate.</strong> The controller does not report a current speed for them, so they are counted but contribute 0 Mbps.</li>
            <li><strong>This is a snapshot, not history.</strong> Nothing is recorded over time yet, so there is no "how was it yesterday". Polling on a schedule would add that.</li>
            <li><strong>Refresh</strong> clears the one-minute cache, so pressing it twice within a minute is the same as pressing it once.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
