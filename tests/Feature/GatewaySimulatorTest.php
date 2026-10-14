<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gateway simulator must never be reachable in production, and must refuse
 * anyone who is not a registry administrator.
 */
class GatewaySimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_simulator_is_not_routable_when_disabled(): void
    {
        // The default in testing/local only; production leaves it off.
        config(['portal.simulator_enabled' => false]);

        $this->get('/simulator')->assertNotFound();
        // Must be a clean refusal, not a CSRF failure from the web group.
        $this->post('/simulator/connect', [])->assertNotFound();
    }

    public function test_the_simulator_refuses_users_who_are_not_signed_in(): void
    {
        config(['portal.simulator_enabled' => true]);

        $this->get('/simulator')->assertForbidden();
    }

    public function test_the_simulator_refuses_users_without_registry_role(): void
    {
        config(['portal.simulator_enabled' => true]);

        $analyst = User::factory()->create([
            'role' => UserRole::Analyst,
            'scope_type' => ScopeType::All,
        ]);

        $this->actingAs($analyst)->get('/simulator')->assertForbidden();
    }

    public function test_a_registry_administrator_can_open_the_simulator(): void
    {
        config(['portal.simulator_enabled' => true]);

        $admin = User::factory()->create([
            'role' => UserRole::DictAdmin,
            'scope_type' => ScopeType::All,
        ]);

        $this->actingAs($admin)
            ->get('/simulator')
            ->assertOk()
            ->assertSee('Gateway simulator');
    }
}
