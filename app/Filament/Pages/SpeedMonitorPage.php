<?php

namespace App\Filament\Pages;

use App\Services\Omada\OmadaSpeedMonitor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Live throughput per location, from the controller's current client list.
 *
 * This is a snapshot, not a trend: the controller keeps no history of speed,
 * so "monitoring" here means refreshing the page. To get trends, poll this
 * on a schedule and store the results.
 */
class SpeedMonitorPage extends Page
{
    protected string $view = 'filament.pages.speed-monitor';

    protected static ?string $navigationLabel = 'Speed monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

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
        $monitor = app(OmadaSpeedMonitor::class);
        $this->snapshot = $monitor->snapshot();
        $this->refreshedAt = now()->toDateTimeString();
    }

    public function refresh(): void
    {
        app(OmadaSpeedMonitor::class)->refresh();
        $this->load();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'snapshot' => $this->snapshot,
            'refreshedAt' => $this->refreshedAt,
        ];
    }

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
                ->label('Download snapshot')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->url(route('speed.monitor.download')),
        ];
    }
}
