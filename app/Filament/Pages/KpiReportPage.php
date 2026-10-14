<?php

namespace App\Filament\Pages;

use App\Enums\AttributionMethod;
use App\Models\Site;
use App\Models\Supplier;
use App\Services\Reporting\KpiReport as KpiReportService;
use App\Services\Reporting\ReportFilters;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * FR-REP-001..003, FR-REP-006: response volume, rating and issue breakdown
 * with the filters analysts actually ask for.
 *
 * Filtering is a plain GET form rather than a Filament schema: fewer moving
 * parts, and the same URL can be bookmarked or pasted into a report.
 *
 * Two rules are enforced in the service, not the view: averages always carry
 * their sample size, and rows outside the user's scope are excluded server-side.
 */
class KpiReportPage extends Page
{
    protected string $view = 'filament.pages.kpi-report';

    protected static ?string $navigationLabel = 'KPI report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $sort = 2;

    /** @var array<string, mixed> */
    public array $report = [];

    /** @var array<string, mixed> */
    public array $activeFilters = [];

    public function mount(): void
    {
        $this->activeFilters = array_filter(request()->query());

        // Drop the filters DTO: Livewire properties must be plain data, and the
        // view reads the active filters separately.
        $this->report = collect(app(KpiReportService::class)->for(
            auth()->user(),
            ReportFilters::fromArray($this->activeFilters, (int) config('reporting.positive_rating_threshold')),
        ))->except('filters')->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'totals' => $this->report['totals'] ?? [],
            'bySite' => $this->report['by_site'] ?? [],
            'bySupplier' => $this->report['by_supplier'] ?? [],
            'byIssue' => $this->report['by_issue'] ?? [],
            'daily' => $this->report['daily'] ?? [],
            'activeFilters' => $this->activeFilters,
            'provinces' => Site::query()->visibleTo(auth()->user())->whereNotNull('province')
                ->distinct()->orderBy('province')->pluck('province', 'province')->all(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'sites' => Site::query()->visibleTo(auth()->user())->orderBy('site_code')
                ->pluck('site_code', 'id')->all(),
            'attributions' => collect(AttributionMethod::cases())
                ->mapWithKeys(fn (AttributionMethod $case) => [$case->value => $case->label()])
                ->all(),
            'positiveThreshold' => (int) config('reporting.positive_rating_threshold'),
            'minimumSampleSize' => (int) config('reporting.minimum_sample_size'),
        ];
    }
}
