<?php

namespace Tests;

use App\Enums\AssignmentPolicy;
use App\Enums\SurveyStatus;
use App\Enums\SurveyVersionStatus;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\SurveyVersion;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /**
     * A site with a live global survey, which is what most tests need.
     */
    protected function activeSiteWithSurvey(array $siteAttributes = [], ?array $questions = null): array
    {
        $supplier = Supplier::factory()->create();
        $site = Site::factory()->create([
            'supplier_id' => $supplier->id,
            ...$siteAttributes,
        ]);

        $survey = Survey::factory()->create(['status' => SurveyStatus::Active]);
        $version = SurveyVersion::factory()->create([
            'survey_id' => $survey->id,
            'schema_json' => ['questions' => $questions ?? $this->defaultQuestions()],
            'status' => SurveyVersionStatus::Published,
        ]);
        $survey->update(['published_version_id' => $version->id]);

        SurveyAssignment::create([
            'survey_id' => $survey->id,
            // Global assignment: a site-specific one takes precedence over it.
            'site_id' => null,
            'policy' => AssignmentPolicy::Optional,
            'status' => 'active',
        ]);

        return [$site, $survey, $version];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultQuestions(): array
    {
        return [
            ['key' => 'rating', 'type' => 'rating', 'label' => 'Rating', 'min' => 1, 'max' => 5, 'required' => true],
            ['key' => 'issue_type', 'type' => 'single_choice', 'label' => 'Issue', 'required' => true, 'reports_as_issue_category' => true, 'options' => [
                ['value' => 'none', 'label' => 'None'],
                ['value' => 'slow', 'label' => 'Slow'],
            ]],
            ['key' => 'comment', 'type' => 'short_text', 'label' => 'Comment', 'max_length' => 100],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validAnswers(): array
    {
        return ['rating' => 4, 'issue_type' => 'slow', 'comment' => 'Signal drops near the entrance.'];
    }
}
