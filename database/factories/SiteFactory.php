<?php

namespace Database\Factories;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Models\Site;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_code' => 'SITE-'.strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $this->faker->city().' Free Wi-Fi',
            'region' => 'Region I',
            'province' => $this->faker->randomElement(['Ilocos Norte', 'Ilocos Sur', 'Pangasinan', 'La Union']),
            'municipality' => $this->faker->city(),
            'address_or_landmark' => $this->faker->streetAddress(),
            'latitude' => $this->faker->latitude(14, 19),
            'longitude' => $this->faker->longitude(119, 123),
            'supplier_id' => Supplier::factory(),
            'gateway_vendor' => null,
            'gateway_model' => null,
            'integration_mode' => IntegrationMode::QrCode,
            'integration_status' => IntegrationStatus::Draft,
            'status' => RecordStatus::Active,
            'commissioned_at' => null,
            'last_integration_test_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => RecordStatus::Inactive]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['integration_status' => IntegrationStatus::Disabled]);
    }
}
