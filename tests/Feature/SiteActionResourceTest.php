<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Resources\SiteActions\Pages\ListSiteActions;
use App\Models\SiteAction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteActionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_action_queue_hides_actions_outside_the_users_scope(): void
    {
        [$ownSite] = $this->activeSiteWithSurvey();
        [$otherSite] = $this->activeSiteWithSurvey();

        $own = SiteAction::create(['site_id' => $ownSite->id]);
        $other = SiteAction::create(['site_id' => $otherSite->id]);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $ownSite->supplier_id,
        ]);

        Livewire::actingAs($user)
            ->test(ListSiteActions::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_only_one_operational_action_can_exist_per_site(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        SiteAction::create(['site_id' => $site->id]);

        $this->expectException(QueryException::class);

        SiteAction::create(['site_id' => $site->id]);
    }
}
