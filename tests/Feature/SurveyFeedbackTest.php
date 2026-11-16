<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Reporting\ReportDocument;
use App\Services\Reporting\ReportFilters;
use App\Services\Surveys\FeedbackQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The experience dropdown and its short follow-up.
 *
 * Free text was the noisiest thing in the report, so the common answers are
 * countable now, and the only place someone types is when they pick
 * "Something else".
 */
class SurveyFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dropdown_is_offered_and_the_answer_is_stored(): void
    {
        [$site] = $this->siteWithFeedback();

        $html = (string) $this->get(route('site.survey', ['siteCode' => $site->site_code]))->getContent();

        $this->assertStringContainsString('<select id="feedback"', $html);
        $this->assertStringContainsString('Something else (I will describe it)', $html);

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 4,
                'issue_type' => 'none',
                'feedback' => 'satisfied',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();

        $this->assertSame('satisfied', $stored->feedback);
        $this->assertSame('satisfied', $stored->answers_json['feedback']);
        $this->assertArrayNotHasKey('feedback_other', $stored->answers_json);
    }

    public function test_the_follow_up_box_is_only_stored_when_something_else_was_chosen(): void
    {
        [$site] = $this->siteWithFeedback();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 4,
                'issue_type' => 'none',
                'feedback' => 'dissatisfied',
                'feedback_other' => 'The portal keeps dropping.',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();

        $this->assertArrayNotHasKey('feedback_other', $stored->answers_json);
    }

    public function test_a_personal_comment_is_stored_when_something_else_was_chosen(): void
    {
        [$site] = $this->siteWithFeedback();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 2,
                'issue_type' => 'slow',
                'feedback' => 'other',
                'feedback_other' => 'The portal keeps dropping every minute.',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();

        $this->assertSame('other', $stored->feedback);
        $this->assertSame('The portal keeps dropping every minute.', $stored->answers_json['feedback_other']);
    }

    public function test_a_comment_longer_than_the_limit_is_refused(): void
    {
        [$site] = $this->siteWithFeedback();

        $response = $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 2,
                'issue_type' => 'slow',
                'feedback' => 'other',
                'feedback_other' => str_repeat('a', FeedbackQuestions::MAX_COMMENT_LENGTH + 1),
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertSessionHasErrors('answers.feedback_other');
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_the_report_counts_the_dropdown_answers(): void
    {
        [$site] = $this->siteWithFeedback();

        foreach (['satisfied', 'satisfied', 'neutral'] as $feedback) {
            $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
                'answers' => ['rating' => 4, 'issue_type' => 'none', 'feedback' => $feedback],
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        }

        $document = app(ReportDocument::class)->build(
            User::factory()->create(['scope_type' => ScopeType::All]),
            ReportFilters::fromArray([], (int) config('reporting.positive_rating_threshold')),
        );

        $rows = collect($document['by_feedback'])->keyBy('value');

        $this->assertSame(2, $rows['satisfied']['total']);
        $this->assertSame(1, $rows['neutral']['total']);
        $this->assertSame('Satisfied, thank you', $rows['satisfied']['label']);
    }

    /**
     * A live site whose survey carries the experience dropdown.
     *
     * @return array{0: Site}
     */
    private function siteWithFeedback(): array
    {
        [$site] = $this->activeSiteWithSurvey(
            ['site_code' => 'FEEDBACK-SITE', 'name' => 'Feedback Site'],
            array_merge($this->defaultQuestions(), FeedbackQuestions::questions()),
        );

        return [$site];
    }
}
