<?php

namespace App\Filament\Pages;

use App\Services\Sites\SiteHealth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * A colour-coded view of every location the user may see.
 *
 * This deliberately combines three separate questions instead of inventing a
 * single "network health" verdict without evidence:
 *
 * - What people report (average rating and issue rate)
 * - What devices report (latest timed link-rate sample)
 * - Whether the portal has ever reached the site
 *
 * The score is a guide for triage, not a claim about internet bandwidth or
 * service-level compliance.
 */
class SitePerformancePage extends Page
{
    protected string $view = 'filament.pages.site-performance';

    protected static ?string $navigationLabel = 'Performance map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $title = 'Site performance map';

    protected ?string $subheading = 'Find the locations that need attention first.';

    protected static ?int $sort = 6;

    public string $search = '';

    public string $province = '';

    public string $health = '';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [
                'cards' => [],
                'provinces' => [],
                'counts' => ['critical' => 0, 'warning' => 0, 'good' => 0, 'nodata' => 0],
                'healthOrder' => ['critical', 'warning', 'good', 'nodata'],
            ];
        }

        $cards = app(SiteHealth::class)->withActions(app(SiteHealth::class)->cards($user));
        $provinces = collect($cards)->pluck('site.province')->filter()->unique()->sort()->values()->all();

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $cards = array_values(array_filter($cards, fn (array $card): bool => str_contains($card['searchable'], $needle)));
        }

        if ($this->province !== '') {
            $cards = array_values(array_filter($cards, fn (array $card): bool => $card['site']->province === $this->province));
        }

        if ($this->health !== '') {
            $cards = array_values(array_filter($cards, fn (array $card): bool => $card['health'] === $this->health));
        }

        $health = app(SiteHealth::class);
        usort($cards, fn (array $a, array $b): int => $health->rank($a['health']) <=> $health->rank($b['health']) ?: $a['score'] <=> $b['score'] ?: strcmp($a['site']->site_code, $b['site']->site_code));

        $healthOrder = ['critical', 'warning', 'good', 'nodata'];
        $counts = array_count_values(array_column($cards, 'health'));

        return [
            'cards' => $cards,
            'provinces' => $provinces,
            'counts' => [
                'critical' => $counts['critical'] ?? 0,
                'warning' => $counts['warning'] ?? 0,
                'good' => $counts['good'] ?? 0,
                'nodata' => $counts['nodata'] ?? 0,
            ],
            'healthOrder' => $healthOrder,
        ];
    }
}
