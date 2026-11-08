<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Pages\SitePerformancePage;
use App\Models\Site;
use App\Models\SpeedSiteSample;
use App\Models\Supplier;
use App\Models\SurveyResponse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SitePerformancePageTest extends TestCase
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

    public function test_it_shows_the_site_triage_card(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);
        $site = Site::factory()->create([
            'supplier_id' => $supplier->id,
            'site_code' => 'TEST-MAP-001',
            'name' => 'Test Map Site',
            'province' => 'Cagayan',
            'portal_last_seen_at' => now(),
        ]);

        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $supplier->id,
            'overall_rating' => 5,
            'issue_category' => 'none',
        ]);

        SpeedSiteSample::create([
            'site_id' => $site->id,
            'captured_at' => now(),
            'client_count' => 1,
            'wireless_count' => 1,
            'wired_count' => 0,
            'low_rate_wireless_count' => 0,
            'ap_to_client_max_kbps' => 150_000,
            'client_to_ap_max_kbps' => 72_000,
        ]);

        Livewire::test(SitePerformancePage::class)
            ->assertOk()
            ->assertSee('TEST-MAP-001')
            ->assertSee('Test Map Site')
            ->assertSee('Good');
    }
}
