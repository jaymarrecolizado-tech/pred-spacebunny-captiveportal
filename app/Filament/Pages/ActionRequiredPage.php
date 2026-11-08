<?php

namespace App\Filament\Pages;

use App\Services\Sites\SiteHealth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * A work queue: what needs attention, based on evidence in this platform.
 *
 * This is not a guarantee of internet capacity. It deliberately separates
 * evidence of a reported/radio problem from simply having no measurement.
 */
class ActionRequiredPage extends Page
{
    protected string $view = 'filament.pages.action-required';

    protected static ?string $navigationLabel = 'Action required';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $title = 'Action required';

    protected ?string $subheading = 'The sites to check first, with the reason for each.';

    protected static ?int $sort = 5;

    public string $search = '';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $cards = $user === null ? [] : app(SiteHealth::class)->withActions(app(SiteHealth::class)->cards($user));

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $cards = array_values(array_filter($cards, fn (array $card): bool => str_contains($card['searchable'], $needle)));
        }

        $evidence = array_values(array_filter($cards, fn (array $card): bool => in_array($card['health'], ['critical', 'warning'], true)));
        $noData = array_values(array_filter($cards, fn (array $card): bool => $card['health'] === 'nodata'));

        return [
            'evidence' => $evidence,
            'noData' => $noData,
            'criticalCount' => count(array_filter($evidence, fn (array $card): bool => $card['health'] === 'critical')),
            'warningCount' => count(array_filter($evidence, fn (array $card): bool => $card['health'] === 'warning')),
            'noDataCount' => count($noData),
        ];
    }
}
