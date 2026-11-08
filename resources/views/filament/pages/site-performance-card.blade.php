{{-- One triage card, shared by the performance map and the action-required queue. --}}
@php
    $palette = [
        'critical' => ['stripe' => '#b91c1c', 'fill' => '#fef2f2', 'label' => 'Critical', 'badge' => 'danger'],
        'warning' => ['stripe' => '#b45309', 'fill' => '#fffbeb', 'label' => 'Needs attention', 'badge' => 'warning'],
        'good' => ['stripe' => '#15803d', 'fill' => '#f0fdf4', 'label' => 'Good', 'badge' => 'success'],
        'nodata' => ['stripe' => '#64748b', 'fill' => '#f8fafc', 'label' => 'No data', 'badge' => 'gray'],
    ][$card['health']];
    $site = $card['site'];
    $hasCoordinates = is_numeric($site->latitude) && is_numeric($site->longitude);
@endphp

<article style="border:1px solid rgba(128,128,128,.22);border-left:4px solid {{ $palette['stripe'] }};border-radius:.6rem;padding:.85rem;background:{{ $palette['fill'] }};display:flex;flex-direction:column;gap:.55rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
        <div style="min-width:0;">
            <p style="margin:0;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $site->site_code }}</p>
            <h2 style="margin:.15rem 0 0;font-size:1rem;line-height:1.25;">{{ $site->name }}</h2>
        </div>
        <x-filament::badge :color="$palette['badge']">{{ $palette['label'] }}</x-filament::badge>
    </div>

    <p style="margin:0;font-size:.78rem;opacity:.78;">
        {{ collect([$site->municipality, $site->province])->filter()->implode(', ') ?: 'Location not set' }}@if ($card['supplier']) &middot; {{ $card['supplier'] }}@endif
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.35rem;font-size:.8rem;">
        <div><span style="display:block;opacity:.65;font-size:.68rem;">Rating</span><strong>{{ $card['average_rating'] === null ? '—' : $card['average_rating'].' / 5' }} <span style="font-weight:400;opacity:.7;">(n={{ $card['rated'] }})</span></strong></div>
        <div><span style="display:block;opacity:.65;font-size:.68rem;">Issue rate</span><strong>{{ $card['issue_rate'] === null ? '—' : $card['issue_rate'].'%' }}</strong></div>
        <div><span style="display:block;opacity:.65;font-size:.68rem;">Low-rate clients</span><strong>{{ $card['low_rate'] }}</strong></div>
        <div><span style="display:block;opacity:.65;font-size:.68rem;">Portal</span><strong>{{ $card['portal_seen'] ? 'Seen' : 'Never seen' }}</strong></div>
    </div>

    @if ($card['sample_error'])
        <p style="margin:0;color:#b91c1c;font-size:.76rem;">Latest radio sample: {{ $card['sample_error'] }}</p>
    @endif

    <div style="margin-top:auto;display:flex;justify-content:space-between;align-items:center;gap:.5rem;border-top:1px solid rgba(128,128,128,.18);padding-top:.5rem;">
        <span style="font-size:.72rem;opacity:.7;">Score for triage: <strong>{{ $card['score'] }}/100</strong></span>
        <span style="display:flex;gap:.35rem;white-space:nowrap;">
            <x-filament::button tag="a" size="sm" color="gray" outlined href="{{ url('/admin/kpi-report-page?site_id='.$site->id) }}">KPI</x-filament::button>
            @if ($hasCoordinates)
                <x-filament::button tag="a" size="sm" color="gray" outlined href="https://www.openstreetmap.org/?mlat={{ $site->latitude }}&mlon={{ $site->longitude }}#map=17/{{ $site->latitude }}/{{ $site->longitude }}">Map</x-filament::button>
            @endif
            <x-filament::button tag="a" size="sm" color="gray" outlined href="{{ url('/admin/sites/'.$site->id.'/edit') }}">Manage</x-filament::button>
        </span>
    </div>
</article>
