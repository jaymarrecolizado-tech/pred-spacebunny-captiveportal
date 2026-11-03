<?php

namespace App\Filament\Widgets;

use App\Services\Omada\OmadaSpeedMonitor;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;

/**
 * One row per connected wireless device, across every location.
 *
 * Wired clients are deliberately left out: the controller reports no current
 * rate for them, so a row would always read 0 Mbps and mislead.
 */
class ClientThroughputTable extends TableWidget
{
    private const NUMERIC = ['rxRate', 'txRate', 'rssi', 'signal'];

    protected int|string|array $columnSpan = 'full';

    /** Render with the page: both tables share one cached snapshot. */
    protected static bool $isLazy = false;

    #[On('speed-snapshot-refreshed')]
    public function refreshData(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading('Wireless devices right now')
            ->description('Current rate per device. Signal is the strength at the access point, in dBm.')
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->pages(
                $this->flatten(app(OmadaSpeedMonitor::class)->snapshot()),
                $search,
                $sortColumn ?? 'rxRate',
                $sortDirection ?? 'desc',
                $page,
                $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('site_code')
                    ->label('Site')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Device')
                    ->description(fn (array $record): string => (string) ($record['mac'] ?? ''))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ssid')
                    ->label('SSID')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ap')
                    ->label('Access point')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rxRate')
                    ->label('Download (Mbps)')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (int|string $state): string => number_format(((int) $state) / 1000, 1))
                    ->sortable(),
                TextColumn::make('txRate')
                    ->label('Upload (Mbps)')
                    ->alignEnd()
                    ->formatStateUsing(fn (int|string $state): string => number_format(((int) $state) / 1000, 1))
                    ->sortable(),
                TextColumn::make('rssi')
                    ->label('Signal (dBm)')
                    ->alignEnd()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('signal')
                    ->label('Rank')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                // A weak signal explains a slow row, so it is worth a colour.
                IconColumn::make('quality')
                    ->label('Quality')
                    ->state(fn (array $record): string => match (true) {
                        ($record['rssi'] ?? 0) >= -60 => 'good',
                        ($record['rssi'] ?? 0) >= -70 => 'fair',
                        default => 'weak',
                    })
                    ->icon(fn (string $state): string => $state === 'good' ? 'heroicon-o-wifi' : 'heroicon-o-signal')
                    ->color(fn (string $state): string => match ($state) {
                        'good' => 'success',
                        'fair' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'good' => 'Strong signal',
                        'fair' => 'Usable, but not strong',
                        default => 'Weak signal: expect a slow connection',
                    })
                    ->toggleable(),
            ])
            ->defaultSort('rxRate', 'desc')
            ->searchPlaceholder('Device, SSID or site')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->stackedOnMobile()
            ->emptyStateHeading('No wireless devices connected')
            ->emptyStateDescription('Nobody is on the Wi-Fi at the moment, or the controller could not be reached.')
            ->emptyStateIcon('heroicon-o-wifi');
    }

    /**
     * One flat row per wireless client, carrying its site with it.
     *
     * @param  array<int, array<string, mixed>>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    private function flatten(array $snapshot): array
    {
        $rows = [];

        foreach ($snapshot as $site) {
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

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function pages(array $rows, ?string $search, string $column, string $direction, int|string $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $rows = collect($rows);

        if (filled($search)) {
            $needle = mb_strtolower($search);

            $rows = $rows->filter(fn (array $row): bool => str_contains(
                mb_strtolower($row['name'].' '.$row['ssid'].' '.$row['site_code']),
                $needle,
            ));
        }

        $rows = $rows
            ->sortBy(
                fn (array $row): int|string => in_array($column, self::NUMERIC, true)
                    ? (int) ($row[$column] ?? 0)
                    : mb_strtolower((string) ($row[$column] ?? '')),
                SORT_REGULAR,
                $direction === 'desc',
            )
            ->values();

        $perPage = $recordsPerPage === 'all' ? max(1, $rows->count()) : (int) $recordsPerPage;
        $currentPage = max(1, (int) $page);

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $currentPage,
        );
    }
}
