<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ClientThroughputTable;
use App\Filament\Widgets\SiteThroughputTable;
use App\Models\SpeedSiteSample;
use App\Services\Omada\OmadaSpeedMonitor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Live wireless link rate per location, from the controller's current client list.
 *
 * This is not measured internet throughput: Omada reports the negotiated radio
 * rate per client, in Kbps. AP -> Device is the controller transmitting to the
 * client (txRate); Device -> AP is the reverse (rxRate).
 *
 * Timed samples are stored by `omada:speed-sample` (normally run every five
 * minutes by the Laravel scheduler). Those samples are the only rows on this
 * page that represent actual traffic, and they use cumulative client counters.
 *
 * The two tables are widgets (SiteThroughputTable, ClientThroughputTable) so
 * they get Filament's sorting, search, pagination and phone layout for free.
 */
class SpeedMonitorPage extends Page
{
    protected string $view = 'filament.pages.speed-monitor';

    protected static ?string $navigationLabel = 'Wi-Fi link rates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $title = 'Wi-Fi link rates';

    protected ?string $subheading = 'A live snapshot of the radio rates negotiated by clients right now.';

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
                ->label('Download snapshot')
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

        $since = now()->subHour();
        $actual = SpeedSiteSample::query()
            ->where('captured_at', '>=', $since)
            ->whereNotNull('delta_down_bytes')
            ->selectRaw('SUM(delta_down_bytes) as downloaded, SUM(delta_up_bytes) as uploaded, SUM(down_mbps) as down_total, SUM(up_mbps) as up_total, COUNT(DISTINCT site_id) as sampled_sites, SUM(matched_clients) as matched_clients')
            ->first();
        $actualDown = (int) ($actual?->downloaded ?? 0);
        $actualUp = (int) ($actual?->uploaded ?? 0);
        $sampledSites = (int) ($actual?->sampled_sites ?? 0);
        $matchedClients = (int) ($actual?->matched_clients ?? 0);
        $lastSample = SpeedSiteSample::latest('captured_at')->first();

        return [
            'refreshedAt' => $this->refreshedAt,
            'locations' => count($snapshot),
            'clients' => array_sum(array_column($snapshot, 'client_count')),
            'wireless' => array_sum(array_column($snapshot, 'wireless_count')),
            'wired' => array_sum(array_column($snapshot, 'wired_count')),
            'peakApToClientMbps' => count($snapshot) === 0 ? 0 : max(array_column($snapshot, 'ap_to_client_max_kbps')) / 1000,
            'peakClientToApMbps' => count($snapshot) === 0 ? 0 : max(array_column($snapshot, 'client_to_ap_max_kbps')) / 1000,
            'lowRateDevices' => array_sum(array_column($snapshot, 'low_rate_wireless_count')),
            'reachable' => count(array_filter($snapshot, fn (array $site): bool => empty($site['error']))),
            'actualDownMb' => $actualDown / 1048576,
            'actualUpMb' => $actualUp / 1048576,
            'actualDownMbps' => $sampledSites === 0 ? null : ((float) ($actual?->down_total ?? 0) / $sampledSites),
            'actualUpMbps' => $sampledSites === 0 ? null : ((float) ($actual?->up_total ?? 0) / $sampledSites),
            'sampledSites' => $sampledSites,
            'matchedClients' => $matchedClients,
            'lastSampleAt' => $lastSample?->captured_at?->toDateTimeString() ?? 'No samples yet',
        ];
    }
}
