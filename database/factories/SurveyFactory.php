<?php

namespace Database\Factories;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' feedback',
            'description' => $this->faker->sentence(),
            'status' => SurveyStatus::Active,
            'scope_type' => 'global',
            'published_version_id' => null,
        ];
    }
}
