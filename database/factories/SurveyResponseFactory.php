<?php

namespace Database\Factories;

use App\Enums\AttributionMethod;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'supplier_id' => fn (array $attributes) => Site::find($attributes['site_id'])?->supplier_id,
            'survey_version_id' => SurveyVersion::factory(),
            'attribution_method' => AttributionMethod::QrCode,
            'session_reference_hash' => null,
            'idempotency_key_hash' => null,
            'answers_json' => ['rating' => $this->faker->numberBetween(1, 5)],
            'overall_rating' => $this->faker->numberBetween(1, 5),
            'issue_category' => $this->faker->randomElement(['none', 'slow', 'drops', 'no_access']),
            'submitted_at' => now(),
            'created_at' => now(),
        ];
    }
}
