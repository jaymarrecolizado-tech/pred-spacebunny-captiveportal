<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Demo data creates `admin@example.test` / `password` with SuperAdmin rights.
 * If that ever reaches a deployed environment the platform is compromised on
 * the day it goes live — and the README's own setup instructions tell an
 * operator to run `migrate:fresh --seed`. These tests make that impossible to
 * do by accident.
 */
class DemoSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(string $seeder): void
    {
        /** @var Seeder $instance */
        $instance = new $seeder;
        $instance->setContainer($this->app);
        $instance->run();
    }

    public function test_demo_data_is_seeded_locally(): void
    {
        $this->app['env'] = 'local';

        $this->runSeeder(DatabaseSeeder::class);

        $this->assertSame(1, Site::query()->where('site_code', 'DEMO-0001')->count());
        $this->assertTrue(User::query()->where('email', 'admin@example.test')->exists());
        $this->assertSame(1, Integration::count());
    }

    public function test_the_database_seeder_skips_demo_data_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->runSeeder(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Site::count());
        $this->assertSame(0, Integration::count());
    }

    public function test_the_database_seeder_skips_demo_data_in_staging(): void
    {
        $this->app['env'] = 'staging';

        $this->runSeeder(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Site::count());
    }

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        // Reached directly, the seeder fails loudly rather than skipping: a
        // silent skip would let a deploy look successful while producing a
        // half-configured platform.
        $this->expectException(RuntimeException::class);

        $this->runSeeder(DemoSeeder::class);
    }

    public function test_the_demo_seeder_refuses_to_run_in_staging(): void
    {
        $this->app['env'] = 'staging';

        $this->expectException(RuntimeException::class);

        $this->runSeeder(DemoSeeder::class);
    }

    public function test_the_seed_command_is_safe_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        // The operator path an automated deploy would use.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        foreach (['admin@example.test', 'supplier@example.test'] as $email) {
            $this->assertFalse(
                User::query()->where('email', $email)->exists(),
                "The demo account {$email} must never exist outside local development.",
            );
        }
    }
}
