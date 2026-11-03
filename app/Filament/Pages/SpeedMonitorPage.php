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

    // --- Per-site table state ---
    public string $siteSortColumn = 'rx_rate';

    public string $siteSortDirection = 'desc';

    public string $siteSearch = '';

    public string $siteProvinceFilter = '';

    public int $sitePerPage = 25;

    public int $sitePage = 1;

    // --- Per-client table state ---
    public string $clientSortColumn = 'rxRate';

    public string $clientSortDirection = 'desc';

    public string $clientSearch = '';

    public string $clientSiteFilter = '';

    public int $clientPerPage = 25;

    public int $clientPage = 1;

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

    public function updatedSiteSortColumn(): void
    {
        $this->sitePage = 1;
    }

    public function updatedSiteSortDirection(): void
    {
        $this->sitePage = 1;
    }

    public function updatedSiteSearch(): void
    {
        $this->sitePage = 1;
    }

    public function updatedSiteProvinceFilter(): void
    {
        $this->sitePage = 1;
    }

    public function updatedSitePerPage(): void
    {
        $this->sitePage = 1;
    }

    public function updatedClientSortColumn(): void
    {
        $this->clientPage = 1;
    }

    public function updatedClientSortDirection(): void
    {
        $this->clientPage = 1;
    }

    public function updatedClientSearch(): void
    {
        $this->clientPage = 1;
    }

    public function updatedClientSiteFilter(): void
    {
        $this->clientPage = 1;
    }

    public function updatedClientPerPage(): void
    {
        $this->clientPage = 1;
    }

    public function sortSite(string $column): void
    {
        if ($this->siteSortColumn === $column) {
            $this->siteSortDirection = $this->siteSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->siteSortColumn = $column;
            $this->siteSortDirection = 'desc';
        }

        $this->sitePage = 1;
    }

    public function sortClient(string $column): void
    {
        if ($this->clientSortColumn === $column) {
            $this->clientSortDirection = $this->clientSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->clientSortColumn = $column;
            $this->clientSortDirection = 'desc';
        }

        $this->clientPage = 1;
    }

    public function prevSitePage(): void
    {
        $this->sitePage = max(1, $this->sitePage - 1);
    }

    public function nextSitePage(): void
    {
        $total = max(1, ceil($this->siteTotal / $this->sitePerPage));
        $this->sitePage = min($total, $this->sitePage + 1);
    }

    public function prevClientPage(): void
    {
        $this->clientPage = max(1, $this->clientPage - 1);
    }

    public function nextClientPage(): void
    {
        $total = max(1, ceil($this->clientTotal / $this->clientPerPage));
        $this->clientPage = min($total, $this->clientPage + 1);
    }

    /**
     * Filtered, sorted, paginated per-site rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSiteRowsProperty(): array
    {
        $rows = $this->snapshot;

        if ($this->siteSearch !== '') {
            $needle = strtolower($this->siteSearch);
            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                return str_contains(strtolower($row['site_code']), $needle)
                    || str_contains(strtolower($row['site_name']), $needle);
            }));
        }

        if ($this->siteProvinceFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['province'] === $this->siteProvinceFilter));
        }

        $column = $this->siteSortColumn;
        $direction = $this->siteSortDirection === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($column, $direction): int {
            $va = $a[$column] ?? 0;
            $vb = $b[$column] ?? 0;

            if (is_string($va) && is_string($vb)) {
                return $direction * strcmp($va, $vb);
            }

            return $direction * ($va <=> $vb);
        });

        $offset = ($this->sitePage - 1) * $this->sitePerPage;

        return array_slice($rows, $offset, $this->sitePerPage);
    }

    /**
     * Total number of filtered per-site rows (for pagination).
     */
    public function getSiteTotalProperty(): int
    {
        $rows = $this->snapshot;

        if ($this->siteSearch !== '') {
            $needle = strtolower($this->siteSearch);
            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                return str_contains(strtolower($row['site_code']), $needle)
                    || str_contains(strtolower($row['site_name']), $needle);
            }));
        }

        if ($this->siteProvinceFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['province'] === $this->siteProvinceFilter));
        }

        return count($rows);
    }

    /**
     * All per-client rows, flattened across sites.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getClientRowsProperty(): array
    {
        $rows = [];

        foreach ($this->snapshot as $site) {
            foreach ($site['clients'] as $client) {
                if (! $client['wireless']) {
                    continue;
                }

                $rows[] = [
                    'site_code' => $site['site_code'],
                    'name' => $client['name'] ?: $client['mac'],
                    'mac' => $client['mac'],
                    'ssid' => $client['ssid'],
                    'ap' => $client['ap'],
                    'rxRate' => $client['rxRate'],
                    'txRate' => $client['txRate'],
                    'rssi' => $client['rssi'],
                    'signal' => $client['signal'],
                ];
            }
        }

        if ($this->clientSearch !== '') {
            $needle = strtolower($this->clientSearch);
            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                return str_contains(strtolower($row['name']), $needle)
                    || str_contains(strtolower($row['ssid']), $needle)
                    || str_contains(strtolower($row['site_code']), $needle);
            }));
        }

        if ($this->clientSiteFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['site_code'] === $this->clientSiteFilter));
        }

        $column = $this->clientSortColumn;
        $direction = $this->clientSortDirection === 'asc' ? 1 : -1;

        usort($rows, function (array $a, array $b) use ($column, $direction): int {
            $va = $a[$column] ?? 0;
            $vb = $b[$column] ?? 0;

            if (is_string($va) && is_string($vb)) {
                return $direction * strcmp($va, $vb);
            }

            return $direction * ($va <=> $vb);
        });

        $offset = ($this->clientPage - 1) * $this->clientPerPage;

        return array_slice($rows, $offset, $this->clientPerPage);
    }

    /**
     * Total number of filtered per-client rows (for pagination).
     */
    public function getClientTotalProperty(): int
    {
        $rows = [];

        foreach ($this->snapshot as $site) {
            foreach ($site['clients'] as $client) {
                if (! $client['wireless']) {
                    continue;
                }

                $rows[] = [
                    'site_code' => $site['site_code'],
                    'name' => $client['name'] ?: $client['mac'],
                    'ssid' => $client['ssid'],
                ];
            }
        }

        if ($this->clientSearch !== '') {
            $needle = strtolower($this->clientSearch);
            $rows = array_values(array_filter($rows, function (array $row) use ($needle): bool {
                return str_contains(strtolower($row['name']), $needle)
                    || str_contains(strtolower($row['ssid']), $needle)
                    || str_contains(strtolower($row['site_code']), $needle);
            }));
        }

        if ($this->clientSiteFilter !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['site_code'] === $this->clientSiteFilter));
        }

        return count($rows);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'snapshot' => $this->snapshot,
            'refreshedAt' => $this->refreshedAt,
            'siteRows' => $this->siteRows,
            'siteTotal' => $this->siteTotal,
            'clientRows' => $this->clientRows,
            'clientTotal' => $this->clientTotal,
            'siteSortColumn' => $this->siteSortColumn,
            'siteSortDirection' => $this->siteSortDirection,
            'siteSearch' => $this->siteSearch,
            'siteProvinceFilter' => $this->siteProvinceFilter,
            'sitePerPage' => $this->sitePerPage,
            'sitePage' => $this->sitePage,
            'clientSortColumn' => $this->clientSortColumn,
            'clientSortDirection' => $this->clientSortDirection,
            'clientSearch' => $this->clientSearch,
            'clientSiteFilter' => $this->clientSiteFilter,
            'clientPerPage' => $this->clientPerPage,
            'clientPage' => $this->clientPage,
            'provinces' => collect($this->snapshot)->pluck('province')->filter()->unique()->sort()->values()->all(),
            'siteCodes' => collect($this->snapshot)->pluck('site_code')->sort()->values()->all(),
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
