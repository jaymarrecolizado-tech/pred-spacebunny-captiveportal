<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data creates accounts with known passwords, so it is local-only.
        // A deployed environment provisions real accounts through the admin
        // panel or an approved provisioning step, never through this seeder.
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn(
                'DemoSeeder skipped: demo data is local-only (app env is "'.app()->environment().'").'
            );

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
