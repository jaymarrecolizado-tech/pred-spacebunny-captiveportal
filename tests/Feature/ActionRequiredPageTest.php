<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Pages\ActionRequiredPage;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SurveyResponse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActionRequiredPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
        ]));
    }

    public function test_it_separates_reported_problems_from_missing_measurement(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

        $weakSite = Site::factory()->create([
            'supplier_id' => $supplier->id,
            'site_code' => 'WEAK-SITE-001',
            'name' => 'Weak Test Site',
            'province' => 'Cagayan',
            'portal_last_seen_at' => now(),
        ]);

        SurveyResponse::factory()->create([
            'site_id' => $weakSite->id,
            'supplier_id' => $supplier->id,
            'overall_rating' => 2,
            'issue_category' => 'slow',
        ]);

        $unmeasuredSite = Site::factory()->create([
            'supplier_id' => $supplier->id,
            'site_code' => 'NO-DATA-001',
            'name' => 'Unmeasured Test Site',
            'province' => 'Cagayan',
        ]);

        Livewire::test(ActionRequiredPage::class)
            ->assertOk()
            ->assertSee('Action required')
            ->assertSee('Network-evidence problems')
            ->assertSee('No measurement yet')
            ->assertSee('WEAK-SITE-001')
            ->assertSee('NO-DATA-001');
    }
}
