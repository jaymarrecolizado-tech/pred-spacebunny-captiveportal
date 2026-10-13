<?php

namespace Tests\Feature;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-ADM-005: administrative changes leave an audit trail.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_site_is_audited(): void
    {
        $user = User::factory()->create(['role' => UserRole::DictAdmin, 'scope_type' => ScopeType::All]);
        $this->actingAs($user);

        $site = Site::factory()->create();

        $log = AuditLog::where('entity_type', Site::class)->where('entity_id', $site->id)->first();

        $this->assertNotNull($log);
        $this->assertSame('created', $log->action);
        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_updating_a_site_records_the_changed_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::DictAdmin, 'scope_type' => ScopeType::All]);
        $this->actingAs($user);

        $site = Site::factory()->create();
        $site->update(['name' => 'Renamed site', 'integration_status' => IntegrationStatus::Active]);

        $log = AuditLog::where('entity_type', Site::class)
            ->where('entity_id', $site->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $changed = array_keys($log->summary['changed']);

        $this->assertContains('name', $changed);
        $this->assertContains('integration_status', $changed);
    }

    public function test_deleting_a_site_is_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::DictAdmin, 'scope_type' => ScopeType::All]));

        $site = Site::factory()->create();
        $site->delete();

        $this->assertTrue(
            AuditLog::where('entity_type', Site::class)
                ->where('entity_id', $site->id)
                ->where('action', 'deleted')
                ->exists()
        );
    }

    public function test_an_update_with_no_changes_writes_nothing(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::DictAdmin, 'scope_type' => ScopeType::All]));

        $site = Site::factory()->create();
        $site->update(['name' => $site->name]);

        $this->assertSame(0, AuditLog::where('action', 'updated')->count());
    }

    public function test_public_submissions_do_not_need_an_audit_entry(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $before = AuditLog::count();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        // Public feedback is not an administrative change.
        $this->assertSame($before, AuditLog::count());
    }

    public function test_integration_mode_changes_are_audited(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::DictAdmin, 'scope_type' => ScopeType::All]));

        $site = Site::factory()->create(['integration_mode' => IntegrationMode::QrCode]);
        $site->update(['integration_mode' => IntegrationMode::CaptivePortal]);

        $log = AuditLog::where('action', 'updated')
            ->where('entity_type', Site::class)
            ->where('entity_id', $site->id)
            ->firstOrFail();

        $this->assertSame('qr_code', $log->summary['changed']['integration_mode']['from']);
        $this->assertSame('captive_portal', $log->summary['changed']['integration_mode']['to']);
    }
}
