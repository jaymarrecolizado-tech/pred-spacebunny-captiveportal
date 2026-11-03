<?php

namespace App\Filament\Widgets;

use App\Services\Omada\OmadaSpeedMonitor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;

/**
 * Throughput per location, as a real Filament table.
 *
 * The controller only exposes the current rate of each connected client, so
 * the rows are a snapshot. They arrive as plain arrays rather than a database
 * query, and Filament's array data source still gives us the standard table:
 * click-to-sort headers, search, pagination, and a stacked layout on phones.
 */
class SiteThroughputTable extends TableWidget
{
    /** Columns whose values are numbers, not words, when sorting. */
    private const NUMERIC = ['client_count', 'wireless_count', 'wired_count', 'rx_rate', 'tx_rate'];

    protected int|string|array $columnSpan = 'full';

    /** Render with the page: both tables share one cached snapshot. */
    protected static bool $isLazy = false;

    /** Re-read the snapshot when the page hits Refresh. */
    #[On('speed-snapshot-refreshed')]
    public function refreshData(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading('Throughput by location')
            ->description('Every location your account can see. Click a column to sort; search to narrow the list.')
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection, int|string $page, int|string $recordsPerPage): LengthAwarePaginator => $this->pages(
                app(OmadaSpeedMonitor::class)->snapshot(),
                $search,
                $sortColumn ?? 'rx_rate',
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
                TextColumn::make('site_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('province')
                    ->label('Province')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('client_count')
                    ->label('Clients')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('wireless_count')
                    ->label('Wireless')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('wired_count')
                    ->label('Wired')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('rx_rate')
                    ->label('Download (Mbps)')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (int|string $state): string => number_format(((int) $state) / 1000, 1))
                    ->sortable(),
                TextColumn::make('tx_rate')
                    ->label('Upload (Mbps)')
                    ->alignEnd()
                    ->formatStateUsing(fn (int|string $state): string => number_format(((int) $state) / 1000, 1))
                    ->sortable(),
                TextColumn::make('error')
                    ->label('Status')
                    ->badge()
                    ->color('danger')
                    ->placeholder('Live')
                    ->toggleable(),
            ])
            ->defaultSort('rx_rate', 'desc')
            ->searchPlaceholder('Site code, name or province')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->stackedOnMobile()
            ->emptyStateHeading('No locations to show')
            ->emptyStateDescription('The controller returned no sites, or your account has none yet.')
            ->emptyStateIcon('heroicon-o-signal-slash');
    }

    /**
     * Filter, sort and slice the snapshot. Filament handles the table chrome;
     * the arithmetic lives here because the rows are not a database query.
     *
     * @param  array<int, array<string, mixed>>  $snapshot
     */
    private function pages(array $snapshot, ?string $search, string $column, string $direction, int|string $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $rows = collect($snapshot);

        if (filled($search)) {
            $needle = mb_strtolower($search);

            $rows = $rows->filter(fn (array $row): bool => str_contains(
                mb_strtolower($row['site_code'].' '.$row['site_name'].' '.($row['province'] ?? '')),
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
        $page = max(1, (int) $page);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
        );
    }
}
