<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ClientThroughputTable;
use App\Filament\Widgets\SiteThroughputTable;
use App\Services\Omada\OmadaSpeedMonitor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Live throughput per location, from the controller's current client list.
 *
 * This is a snapshot, not a trend: the controller keeps no history of speed,
 * so "monitoring" here means pressing Refresh. To get trends, poll this on a
 * schedule and store the results.
 *
 * The two tables are widgets (SiteThroughputTable, ClientThroughputTable) so
 * they get Filament's sorting, search, pagination and phone layout for free.
 */
class SpeedMonitorPage extends Page
{
    protected string $view = 'filament.pages.speed-monitor';

    protected static ?string $navigationLabel = 'Speed monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $title = 'Speed monitor';

    protected ?string $subheading = 'A live snapshot of how fast every location is right now.';

    protected static ?int $sort = 4;

    /** @var array<int, array<string, mixed>> */
    public array $snapshot = [];

    public ?string $refreshedAt = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->snapshot = app(OmadaSpeedMonitor::class)->snapshot();
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function refresh(): void
    {
        app(OmadaSpeedMonitor::class)->refresh();
        $this->load();

        // The tables are their own Livewire components; tell them to re-read.
        $this->dispatch('speed-snapshot-refreshed');
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            SiteThroughputTable::class,
            ClientThroughputTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->action('refresh'),

            Action::make('download')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->url(route('speed.monitor.download')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $snapshot = $this->snapshot;

        return [
            'refreshedAt' => $this->refreshedAt,
            'locations' => count($snapshot),
            'clients' => array_sum(array_column($snapshot, 'client_count')),
            'wireless' => array_sum(array_column($snapshot, 'wireless_count')),
            'wired' => array_sum(array_column($snapshot, 'wired_count')),
            'downloadMbps' => array_sum(array_column($snapshot, 'rx_rate')) / 1000,
            'uploadMbps' => array_sum(array_column($snapshot, 'tx_rate')) / 1000,
            'reachable' => count(array_filter($snapshot, fn (array $site): bool => empty($site['error']))),
        ];
    }
}
