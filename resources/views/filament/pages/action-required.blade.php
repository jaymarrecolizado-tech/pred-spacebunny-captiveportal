{{--
    Action required: the practical work queue for the network team.

    Network-evidence problems are kept separate from locations with no
    usable measurement. A missing survey or missing radio sample is not proof
    that the Wi-Fi is slow, and should not automatically send a technician out.
--}}
<x-filament-panels::page>
    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="How to use this page"
        description="Evidence first. Measurement gaps are configuration work, not proof of poor Wi-Fi."
    >
        <ul style="margin:0;padding-inline-start:1.25rem;display:grid;gap:.35rem;font-size:.85rem;line-height:1.5;">
            <li><strong>Network-evidence problems</strong> use survey rating, reported issues, low-rate wireless clients or a failed radio sample.</li>
            <li><strong>No measurement yet</strong> means this platform has no usable feedback/radio evidence for that location. Check portal and AP configuration before dispatching a technician.</li>
            <li><strong>Never seen</strong> means the controller has not presented this site to the feedback/telemetry service yet.</li>
            <li>Average ratings are always shown with the number of responses behind them.</li>
        </ul>
    </x-filament::section>

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-block:1rem;align-items:center;border:1px solid rgba(128,128,128,.18);border-radius:.6rem;padding:.75rem;">
        <x-filament::input.wrapper style="flex:1 1 12rem;">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search site, name, municipality, province or supplier" class="fi-input">
        </x-filament::input.wrapper>
    </div>

    <div style="display:grid;gap:.85rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));">
        @foreach ([
            ['label' => 'Critical', 'value' => $criticalCount, 'colour' => '#b91c1c', 'fill' => '#fef2f2'],
            ['label' => 'Needs attention', 'value' => $warningCount, 'colour' => '#b45309', 'fill' => '#fffbeb'],
            ['label' => 'No measurement', 'value' => $noDataCount, 'colour' => '#64748b', 'fill' => '#f8fafc'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.22);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.85rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.45rem;font-weight:700;color:{{ $tile['colour'] }};letter-spacing:-.02em;">{{ $tile['value'] }}</div>
                <div style="font-size:.72rem;opacity:.7;margin-top:.25rem;">Locations in this queue</div>
            </div>
        @endforeach
    </div>

    <x-filament::section icon="heroicon-o-exclamation-triangle" heading="Network-evidence problems" description="Check these first.">
        <div style="display:grid;gap:.8rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));">
            @forelse ($evidence as $card)
                @include('filament.pages.site-performance-card', ['card' => $card])
            @empty
                <div style="border:1px solid rgba(128,128,128,.22);border-radius:.6rem;padding:1rem;">
                    No sites match these filters.
                </div>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-signal-slash" heading="No measurement yet" description="Check deployment and configuration before treating these as network faults.">
        <div style="display:grid;gap:.8rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));">
            @forelse ($noData as $card)
                @include('filament.pages.site-performance-card', ['card' => $card])
            @empty
                <div style="border:1px solid rgba(128,128,128,.22);border-radius:.6rem;padding:1rem;">
                    No sites match these filters.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
