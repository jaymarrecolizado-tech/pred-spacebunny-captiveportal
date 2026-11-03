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
            ['label' => 'Locations', 'value' => $locations, 'note' => $reachable === $locations ? 'All reachable' : ($reachable.' of '.$locations.' reachable'), 'colour' => '#1d4ed8', 'fill' => '#eff6ff'],
            ['label' => 'Connected clients', 'value' => $clients, 'note' => 'Devices the controller can see', 'colour' => '#0891b2', 'fill' => '#ecfeff'],
            ['label' => 'Wireless', 'value' => $wireless, 'note' => 'Wireless link rates shown', 'colour' => '#7c3aed', 'fill' => '#f5f3ff'],
            ['label' => 'Wired', 'value' => $wired, 'note' => 'No per-client rate reported', 'colour' => '#64748b', 'fill' => '#f1f5f9'],
            ['label' => 'AP → Device link', 'value' => number_format($apToClientMbps, 1).' Mbps', 'note' => 'Σ of every wireless client’s link rate', 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'Device → AP link', 'value' => number_format($clientToApMbps, 1).' Mbps', 'note' => 'Σ of every wireless client’s link rate', 'colour' => '#b45309', 'fill' => '#fffbeb'],
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
            <li><strong>These are link rates, not actual traffic.</strong> The controller reports the radio rate currently negotiated with each client, in Kbps. It is not a speed-test result, not bytes transferred, and not proof of internet capacity.</li>
            <li><strong>AP → Device means data to the device.</strong> Device → AP is data from the device to the Wi-Fi system. The totals add up every wireless client’s current link rate, so they describe radio capacity, not internet speed.</li>
            <li><strong>Wired devices have no wireless rate.</strong> The controller does not report a per-client wireless radio rate for them, so they are counted but contribute nothing to the link-rate totals.</li>
            <li><strong>This is a snapshot, not history.</strong> Nothing is recorded over time yet, so there is no “how was it yesterday?” A timed sample of cumulative counters would show actual traffic rather than negotiated rates.</li>
            <li><strong>Refresh</strong> clears the one-minute cache, so pressing it twice within a minute is the same as pressing it once.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
