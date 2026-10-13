<?php

namespace Database\Factories;

use App\Enums\IntegrationState;
use App\Models\Integration;
use App\Models\Site;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'site_id' => Site::factory(),
            'integration_type' => 'portal_handoff',
            'public_identifier' => Integration::generateIdentifier(),
            'secret' => Integration::generateSecret(),
            'status' => IntegrationState::Active,
            // Default to test mode: an integration must be promoted
            // deliberately before it can represent production access (FR-CAP-007).
            'is_test_mode' => true,
        ];
    }

    public function production(): static
    {
        return $this->state(fn (array $attributes) => ['is_test_mode' => false]);
    }
}
