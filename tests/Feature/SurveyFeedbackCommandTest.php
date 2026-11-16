<?php

namespace Tests\Feature;

use App\Enums\SurveyVersionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFeedbackCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_publishes_the_dropdown_as_a_new_live_version(): void
    {
        [$site, $survey, $live] = $this->activeSiteWithSurvey();

        $this->artisan('survey:add-feedback')->assertSuccessful();

        $survey->refresh();
        $this->assertNotSame($live->id, $survey->published_version_id);

        $questions = $survey->publishedVersion->questions();
        $keys = array_column($questions, 'key');

        $this->assertContains('feedback', $keys);
        $this->assertContains('feedback_other', $keys);

        // The open text box is gone, replaced by the dropdown.
        $this->assertNotContains('comment', $keys);

        // The version people already answered against is untouched.
        $this->assertSame(SurveyVersionStatus::Published, $live->refresh()->status);

        // Running it twice must not pile up versions.
        $this->artisan('survey:add-feedback')->assertSuccessful();
        $this->assertSame(2, $survey->versions()->count());
    }
}
