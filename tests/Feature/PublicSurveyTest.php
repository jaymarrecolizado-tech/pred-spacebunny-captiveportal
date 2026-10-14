<?php

namespace Tests\Feature;

use App\Enums\AssignmentPolicy;
use App\Enums\RecordStatus;
use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\SurveyResponse;
use App\Models\SurveyVersion;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-RESP-001..010 and FR-CAP-002: the public survey path, end to end.
 */
class PublicSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_complete_the_survey(): void
    {
        [$site, , $version] = $this->activeSiteWithSurvey();

        $response = $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect();

        $stored = SurveyResponse::sole();
        $this->assertSame($site->id, $stored->site_id);
        $this->assertSame($version->id, $stored->survey_version_id);
        $this->assertSame($site->supplier_id, $stored->supplier_id);
        $this->assertSame(4, $stored->overall_rating);
        $this->assertSame('slow', $stored->issue_category);
        $this->assertFalse($stored->attribution_method->isVerified());
    }

    public function test_a_disabled_site_cannot_receive_responses(): void
    {
        [$site] = $this->activeSiteWithSurvey(['status' => RecordStatus::Inactive]);

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('site.unavailable'));

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('site.unavailable'));

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_paused_survey_cannot_receive_responses(): void
    {
        [$site, $survey] = $this->activeSiteWithSurvey();
        $survey->update(['status' => SurveyStatus::Paused]);

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('site.unavailable'));

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_retried_request_does_not_create_a_second_response(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $key = (string) Str::uuid();
        $payload = ['answers' => $this->validAnswers(), 'idempotency_key' => $key];

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), $payload);
        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), $payload);

        $this->assertSame(1, SurveyResponse::count());
    }

    public function test_answers_outside_the_published_schema_are_rejected(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => ['rating' => 4, 'issue_type' => 'not_an_option'],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('answers.issue_type');

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_required_question_cannot_be_skipped(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => ['issue_type' => 'slow'],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('answers.rating');

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_bot_honeypot_is_rejected(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_missing_idempotency_key_is_rejected(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
        ])->assertSessionHasErrors('idempotency_key');
    }

    public function test_free_text_is_stripped_of_markup(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => ['rating' => 5, 'issue_type' => 'none', 'comment' => '<script>alert(1)</script>ok'],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $stored = SurveyResponse::sole();
        $this->assertStringNotContainsString('<script>', $stored->answers_json['comment']);
    }

    public function test_submissions_are_rate_limited(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        foreach (range(1, 10) as $ignored) {
            $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
                'answers' => $this->validAnswers(),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(429);
    }

    public function test_the_public_page_does_not_expose_an_unverified_claim(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee($site->name)
            ->assertSee('Send feedback');
    }

    public function test_an_empty_published_version_is_not_offered_to_the_public(): void
    {
        [$site, $survey] = $this->activeSiteWithSurvey();
        $survey->versions()->update(['schema_json' => ['questions' => []]]);

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('site.unavailable'));

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('site.unavailable'));

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_the_qr_poster_renders(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('site.qr', ['siteCode' => $site->site_code]))
            ->assertOk()
            // A printable poster must actually contain a scannable code.
            ->assertSee('<svg', escape: false)
            ->assertSee($site->site_code)
            ->assertSee($site->name);
    }

    public function test_a_site_specific_assignment_overrides_the_global_one(): void
    {
        [$site, $survey] = $this->activeSiteWithSurvey();

        $specific = Survey::factory()->create(['status' => SurveyStatus::Active]);
        $version = SurveyVersion::factory()->create(['survey_id' => $specific->id]);
        $specific->update(['published_version_id' => $version->id]);

        SurveyAssignment::create([
            'survey_id' => $specific->id,
            'site_id' => $site->id,
            'policy' => AssignmentPolicy::Optional,
            'status' => 'active',
        ]);

        $this->assertSame(
            $specific->id,
            app(ResolveSurvey::class)->forSite($site)->survey->id,
        );
    }
}
