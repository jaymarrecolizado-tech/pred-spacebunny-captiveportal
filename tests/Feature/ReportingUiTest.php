<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Exports\SurveyResponseExporter;
use App\Filament\Pages\KpiReportPage;
use App\Filament\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The reporting surfaces must enforce the same scope as the rest of the panel.
 */
class ReportingUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'scope_type' => ScopeType::All]);
    }

    public function test_the_kpi_page_renders_with_figures(): void
    {
        $site = Site::factory()->create();
        SurveyResponse::factory()->create([
            'site_id' => $site->id, 'supplier_id' => $site->supplier_id,
            'overall_rating' => 4, 'issue_category' => 'slow',
        ]);

        Livewire::actingAs($this->admin())
            ->test(KpiReportPage::class)
            ->assertOk()
            ->assertSee('Total responses')
            ->assertSee('Average rating')
            ->assertSee('Connection issue rate')
            // The Filament components must emit their real classes, otherwise the
            // page is unstyled markup and only a browser will reveal it.
            ->assertSee('fi-section', escape: false)
            ->assertSee('fi-ta-header-cell', escape: false)
            ->assertSee('fi-input', escape: false);
    }

    public function test_the_kpi_page_applies_filters_from_the_query_string(): void
    {
        $site = Site::factory()->create(['province' => 'Ilocos Norte']);
        SurveyResponse::factory()->create([
            'site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'overall_rating' => 5,
        ]);

        $other = Site::factory()->create(['province' => 'Pangasinan']);
        SurveyResponse::factory()->create([
            'site_id' => $other->id, 'supplier_id' => $other->supplier_id, 'overall_rating' => 1,
        ]);

        $component = Livewire::actingAs($this->admin())
            ->withQueryParams(['province' => 'Pangasinan'])
            ->test(KpiReportPage::class);

        $component->assertOk();
        $this->assertSame(1, $component->instance()->report['totals']['responses']);
    }

    public function test_a_supplier_scoped_user_never_sees_another_suppliers_figures(): void
    {
        $mine = Site::factory()->create();
        $theirs = Site::factory()->create();

        SurveyResponse::factory()->create(['site_id' => $mine->id, 'supplier_id' => $mine->supplier_id, 'overall_rating' => 5]);
        SurveyResponse::factory()->create(['site_id' => $theirs->id, 'supplier_id' => $theirs->supplier_id, 'overall_rating' => 1]);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $component = Livewire::actingAs($user)->test(KpiReportPage::class);

        $this->assertSame(1, $component->instance()->report['totals']['responses']);
        $this->assertSame(5.0, $component->instance()->report['totals']['average_rating']);

        // The other supplier's site must not be broken out as its own row.
        $labels = array_column($component->instance()->report['by_site'], 'label');
        $this->assertNotContains($theirs->site_code, $labels);
    }

    public function test_the_responses_page_offers_an_export_action(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListSurveyResponses::class)
            ->assertActionExists('export');
    }

    /**
     * Regression guard: the export modal needs Filament's `exports` table. When
     * that migration had not been published, the modal failed to load and the
     * panel showed "Error while loading page" while the test above still passed.
     */
    public function test_the_export_modal_opens(): void
    {
        $this->assertTrue(
            Schema::hasTable('exports'),
            'Filament export migrations must be published and run.',
        );

        Livewire::actingAs($this->admin())
            ->test(ListSurveyResponses::class)
            ->mountAction('export')
            ->assertActionMounted('export');
    }

    /**
     * The panel does not compile a general Tailwind utility set, so a view
     * written with utilities like `grid` or `bg-white` renders as bare,
     * unstyled HTML. The KPI report must stay on Filament components.
     */
    public function test_the_kpi_view_uses_filament_components_not_uncompiled_utilities(): void
    {
        $view = file_get_contents(resource_path('views/filament/pages/kpi-report.blade.php'));

        $this->assertStringContainsString('<x-filament::section', $view);
        $this->assertStringContainsString('class="fi-ta"', $view);

        // Any of these would be silently ignored by the panel stylesheet.
        foreach (['bg-white', 'text-gray-900', 'rounded-xl', 'border-gray-200', 'shadow-sm'] as $utility) {
            $this->assertStringNotContainsString($utility, $view, "[{$utility}] is not a real class in this panel.");
        }
    }

    public function test_the_exporter_only_receives_rows_within_scope(): void
    {
        $mine = Site::factory()->create();
        $theirs = Site::factory()->create();

        $mineResponse = SurveyResponse::factory()->create([
            'site_id' => $mine->id, 'supplier_id' => $mine->supplier_id, 'overall_rating' => 5,
        ]);
        $theirResponse = SurveyResponse::factory()->create([
            'site_id' => $theirs->id, 'supplier_id' => $theirs->supplier_id, 'overall_rating' => 1,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $component = Livewire::actingAs($user)->test(ListSurveyResponses::class);

        $ids = $component->instance()->getFilteredTableQuery()->pluck('id');

        $this->assertTrue($ids->contains($mineResponse->id));
        $this->assertFalse($ids->contains($theirResponse->id));
    }

    public function test_the_exporter_exposes_the_reporting_columns(): void
    {
        $labels = collect(SurveyResponseExporter::getColumns())
            ->map(fn ($column) => $column->getLabel())
            ->all();

        // The export must carry the same facts the KPI report is built on.
        $this->assertContains('Reference', $labels);
        $this->assertContains('Site code', $labels);
        $this->assertContains('Attribution', $labels);
        $this->assertContains('Rating', $labels);
        $this->assertContains('Issue', $labels);
        $this->assertSame(SurveyResponse::class, SurveyResponseExporter::getModel());
    }
}
