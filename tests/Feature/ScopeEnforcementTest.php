<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-ADM-002 and FR-REP-005: the cross-supplier data leak the plan calls a
 * rollout blocker.
 */
class ScopeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role, ScopeType $scope, ?int $supplierId = null, ?string $provinces = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'scope_type' => $scope,
            'scope_supplier_id' => $supplierId,
            'scope_provinces' => $provinces,
            'is_active' => true,
        ]);
    }

    public function test_a_supplier_scoped_user_only_sees_its_own_sites(): void
    {
        [$ownSite] = $this->activeSiteWithSurvey();
        [$otherSite] = $this->activeSiteWithSurvey();

        $user = $this->user(UserRole::SupplierAdmin, ScopeType::Supplier, $ownSite->supplier_id);

        $visible = Site::query()->visibleTo($user)->pluck('id');

        $this->assertTrue($visible->contains($ownSite->id));
        $this->assertFalse($visible->contains($otherSite->id));
    }

    public function test_a_supplier_scoped_user_cannot_read_another_suppliers_response(): void
    {
        [$ownSite] = $this->activeSiteWithSurvey();
        [$otherSite] = $this->activeSiteWithSurvey();

        $own = SurveyResponse::factory()->create(['site_id' => $ownSite->id, 'supplier_id' => $ownSite->supplier_id]);
        $other = SurveyResponse::factory()->create(['site_id' => $otherSite->id, 'supplier_id' => $otherSite->supplier_id]);

        $user = $this->user(UserRole::SupplierAdmin, ScopeType::Supplier, $ownSite->supplier_id);

        $this->assertTrue($user->can('view', $own));
        $this->assertFalse($user->can('view', $other));

        $visible = SurveyResponse::query()->visibleTo($user)->pluck('id');
        $this->assertSame([$own->id], $visible->all());
    }

    public function test_a_province_scoped_user_only_sees_sites_in_its_provinces(): void
    {
        [$inScope] = $this->activeSiteWithSurvey(['province' => 'Ilocos Norte']);
        [$outOfScope] = $this->activeSiteWithSurvey(['province' => 'Pangasinan']);

        $user = $this->user(UserRole::Analyst, ScopeType::Province, null, 'Ilocos Norte,Ilocos Sur');

        $visible = Site::query()->visibleTo($user)->pluck('id');

        $this->assertTrue($visible->contains($inScope->id));
        $this->assertFalse($visible->contains($outOfScope->id));
    }

    public function test_a_province_scoped_user_without_provinces_sees_nothing(): void
    {
        $this->activeSiteWithSurvey();
        $user = $this->user(UserRole::Analyst, ScopeType::Province, null, null);

        $this->assertSame(0, Site::query()->visibleTo($user)->count());
    }

    public function test_a_dict_administrator_sees_every_site(): void
    {
        [$a] = $this->activeSiteWithSurvey();
        [$b] = $this->activeSiteWithSurvey();

        $user = $this->user(UserRole::DictAdmin, ScopeType::All);

        $this->assertSame(2, Site::query()->visibleTo($user)->count());
    }

    public function test_analysts_cannot_change_the_registry(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $user = $this->user(UserRole::Analyst, ScopeType::All);

        $this->assertFalse($user->can('create', Site::class));
        $this->assertFalse($user->can('update', $site));
    }

    public function test_a_supplier_scoped_user_cannot_manage_the_registry(): void
    {
        $site = Site::factory()->create();
        $user = $this->user(UserRole::SupplierAdmin, ScopeType::Supplier, $site->supplier_id);

        $this->assertFalse($user->can('create', Site::class));
        $this->assertFalse($user->can('delete', $site));
    }

    public function test_responses_are_never_editable_through_the_admin(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $response = SurveyResponse::factory()->create(['site_id' => $site->id, 'supplier_id' => $site->supplier_id]);
        $user = $this->user(UserRole::SuperAdmin, ScopeType::All);

        $this->assertFalse($user->can('update', $response));
        $this->assertFalse($user->can('delete', $response));
    }

    public function test_audit_logs_are_limited_to_authorized_roles(): void
    {
        $audit = AuditLog::first() ?? AuditLog::create([
            'action' => 'created',
            'entity_type' => 'Site',
            'entity_id' => 1,
            'created_at' => now(),
        ]);

        $this->assertTrue($this->user(UserRole::SuperAdmin, ScopeType::All)->can('viewAny', AuditLog::class));
        $this->assertTrue($this->user(UserRole::Auditor, ScopeType::All)->can('view', $audit));
        $this->assertFalse($this->user(UserRole::DictAdmin, ScopeType::All)->can('viewAny', AuditLog::class));
        $this->assertFalse($this->user(UserRole::Analyst, ScopeType::All)->can('viewAny', AuditLog::class));
    }

    public function test_a_deactivated_user_loses_panel_access(): void
    {
        $user = $this->user(UserRole::DictAdmin, ScopeType::All);
        $panel = Filament::getPanel('admin');

        $this->assertTrue($user->canAccessPanel($panel));

        $user->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->canAccessPanel($panel));
    }
}
