{{--
    Site performance map: a colour-coded triage view.

    No map library is introduced on purpose. The location registry holds
    coordinates for an external map link, but the operational decision most
    staff need is which site needs attention; the cards answer that directly.

    Only Filament components and inline styles are used because the panel
    ships no general utility set.
--}}
<x-filament-panels::page>
    <div style="display:grid;gap:.85rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr));">
        @foreach ([
            ['label' => 'Critical', 'value' => $counts['critical'], 'colour' => '#b91c1c', 'fill' => '#fef2f2'],
            ['label' => 'Needs attention', 'value' => $counts['warning'], 'colour' => '#b45309', 'fill' => '#fffbeb'],
            ['label' => 'Good', 'value' => $counts['good'], 'colour' => '#15803d', 'fill' => '#f0fdf4'],
            ['label' => 'No data', 'value' => $counts['nodata'], 'colour' => '#64748b', 'fill' => '#f8fafc'],
        ] as $tile)
            <div style="border:1px solid rgba(128,128,128,.22);border-left:4px solid {{ $tile['colour'] }};border-radius:.6rem;padding:.85rem 1rem;background:{{ $tile['fill'] }};">
                <div style="font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;opacity:.7;">{{ $tile['label'] }}</div>
                <div style="font-size:1.45rem;font-weight:700;color:{{ $tile['colour'] }};letter-spacing:-.02em;">{{ $tile['value'] }}</div>
                <div style="font-size:.72rem;opacity:.7;margin-top:.25rem;">Locations in this status</div>
            </div>
        @endforeach
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-block:1rem;align-items:center;border:1px solid rgba(128,128,128,.18);border-radius:.6rem;padding:.75rem;">
        <x-filament::input.wrapper style="flex:1 1 12rem;">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search site, name, municipality, province or supplier" class="fi-input">
        </x-filament::input.wrapper>
        <x-filament::input.wrapper style="min-width:10rem;">
            <x-filament::input.select wire:model.live="province">
                <option value="">All provinces</option>
                @foreach ($provinces as $provinceName)
                    <option value="{{ $provinceName }}">{{ $provinceName }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper style="min-width:10rem;">
            <x-filament::input.select wire:model.live="health">
                <option value="">All statuses</option>
                <option value="critical">Critical</option>
                <option value="warning">Needs attention</option>
                <option value="good">Good</option>
                <option value="nodata">No data</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div style="display:grid;gap:.8rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,16rem),1fr));">
        @forelse ($cards as $card)
            @include('filament.pages.site-performance-card', ['card' => $card])
        @empty
            <div style="grid-column:1/-1;border:1px solid rgba(128,128,128,.22);border-radius:.6rem;padding:1rem;">
                No locations match these filters.
            </div>
        @endforelse
    </div>

    <p class="fi-section-header-description" style="margin-top:.75rem;">
        Score is a triage aid: 50 points from average rating, 30 from reported issues, 20 from the latest radio sample.
        “Never seen” means the controller has not presented that site to this platform yet; it does not prove the Wi-Fi is down.
    </p>
</x-filament-panels::page>
