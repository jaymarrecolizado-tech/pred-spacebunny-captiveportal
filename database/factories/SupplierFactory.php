<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'code' => Str::upper(Str::random(3)).'-'.$this->faker->unique()->numberBetween(1, 9999),
            'name' => $name,
            'status' => RecordStatus::Active,
            'technical_contact_name' => $this->faker->name(),
            'technical_contact_email' => $this->faker->unique()->safeEmail(),
            'notes' => null,
        ];
    }
}
