<?php

namespace Database\Factories;

use App\Enums\SurveyVersionStatus;
use App\Models\Survey;
use App\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyVersion>
 */
class SurveyVersionFactory extends Factory
{
    protected $model = SurveyVersion::class;

    /**
     * The shape a published DICT feedback survey is expected to have: one
     * rating question, one issue category, one optional comment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'version_number' => 1,
            'schema_json' => [
                'questions' => [
                    ['key' => 'rating', 'type' => 'rating', 'label' => 'How is the Wi-Fi working today?', 'min' => 1, 'max' => 5, 'required' => true],
                    ['key' => 'issue_type', 'type' => 'single_choice', 'label' => 'What is the main issue, if any?', 'required' => true, 'reports_as_issue_category' => true, 'options' => [
                        ['value' => 'none', 'label' => 'No problem'],
                        ['value' => 'slow', 'label' => 'Too slow'],
                        ['value' => 'drops', 'label' => 'Keeps dropping'],
                        ['value' => 'no_access', 'label' => 'Cannot connect'],
                    ]],
                    ['key' => 'comment', 'type' => 'short_text', 'label' => 'Anything else?', 'max_length' => 500],
                ],
            ],
            'privacy_notice_version' => 'v1',
            'status' => SurveyVersionStatus::Published,
            'published_at' => now(),
            'published_by' => null,
        ];
    }
}
