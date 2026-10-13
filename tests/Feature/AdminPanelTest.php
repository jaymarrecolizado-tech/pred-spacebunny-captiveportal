<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SurveyResponse;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FR-ADM-001/002/003: the admin panel must enforce the same rules the
 * policies do, and a supplier administrator must not see another supplier's
 * rows in the rendered table (not just in a policy).
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Resource pages resolve their panel through the current panel.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_user_can_log_in_to_the_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => 'password']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_the_site_table_hides_sites_outside_the_users_scope(): void
    {
        [$ownSite] = $this->activeSiteWithSurvey();
        [$otherSite] = $this->activeSiteWithSurvey();

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $ownSite->supplier_id,
        ]);

        Livewire::actingAs($user)
            ->test(ListSites::class)
            ->assertCanSeeTableRecords([$ownSite])
            ->assertCanNotSeeTableRecords([$otherSite]);
    }

    public function test_the_response_table_hides_responses_outside_the_users_scope(): void
    {
        [$ownSite] = $this->activeSiteWithSurvey();
        [$otherSite] = $this->activeSiteWithSurvey();

        $own = SurveyResponse::factory()->create(['site_id' => $ownSite->id, 'supplier_id' => $ownSite->supplier_id]);
        $other = SurveyResponse::factory()->create(['site_id' => $otherSite->id, 'supplier_id' => $otherSite->supplier_id]);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $ownSite->supplier_id,
        ]);

        Livewire::actingAs($user)
            ->test(ListSurveyResponses::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_an_analyst_cannot_open_the_create_site_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Analyst,
            'scope_type' => ScopeType::All,
        ]);

        Livewire::actingAs($user)
            ->test(CreateSite::class)
            ->assertForbidden();
    }

    public function test_a_dict_administrator_can_open_the_create_site_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DictAdmin,
            'scope_type' => ScopeType::All,
        ]);

        Livewire::actingAs($user)
            ->test(CreateSite::class)
            ->assertOk();
    }

    public function test_only_super_administrators_manage_users(): void
    {
        $dictAdmin = User::factory()->create([
            'role' => UserRole::DictAdmin,
            'scope_type' => ScopeType::All,
        ]);

        Livewire::actingAs($dictAdmin)
            ->test(ListSurveys::class)
            ->assertOk();

        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_site_creation_through_the_panel_is_audited(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DictAdmin,
            'scope_type' => ScopeType::All,
        ]);

        Livewire::actingAs($user)
            ->test(CreateSite::class)
            ->fillForm([
                'site_code' => 'PANEL-0001',
                'name' => 'Created in the panel',
                'supplier_id' => Supplier::factory()->create()->id,
                'integration_mode' => 'qr_code',
                'integration_status' => 'draft',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sites', ['site_code' => 'PANEL-0001']);
        $this->assertTrue(
            AuditLog::where('entity_type', Site::class)->where('action', 'created')->exists()
        );
    }
}
