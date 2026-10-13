<?php

namespace Tests\Feature;

use App\Enums\AssignmentPolicy;
use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Models\SurveyAssignment;
use App\Models\SurveyResponse;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Spec 11.1 and 11.4: the public JSON contract.
 */
class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_survey_endpoint_returns_public_safe_content(): void
    {
        [$site, , $version] = $this->activeSiteWithSurvey();

        $this->getJson("/api/v1/public/sites/{$site->site_code}/survey")
            ->assertOk()
            ->assertJsonPath('data.site.site_code', $site->site_code)
            ->assertJsonPath('data.attribution.verified_wifi_session', false)
            ->assertJsonPath('data.survey_version.version', $version->version_number);
    }

    public function test_an_unknown_site_returns_a_public_safe_error(): void
    {
        $this->getJson('/api/v1/public/sites/NOPE-0001/survey')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'site_unavailable')
            ->assertJsonStructure(['error' => ['code', 'message', 'correlation_id']]);
    }

    public function test_the_api_creates_a_response(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->postJson('/api/v1/public/responses', [
            'site_code' => $site->site_code,
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.site_code', $site->site_code)
            ->assertJsonPath('data.attribution_method', 'qr_code');

        $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_the_api_is_idempotent(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $payload = [
            'site_code' => $site->site_code,
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ];

        $first = $this->postJson('/api/v1/public/responses', $payload)->json('data.id');
        $this->postJson('/api/v1/public/responses', $payload)->assertCreated();

        $this->assertSame($first, SurveyResponse::sole()->id);
    }

    public function test_the_api_rejects_answers_outside_the_schema(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->postJson('/api/v1/public/responses', [
            'site_code' => $site->site_code,
            'answers' => ['rating' => 99, 'issue_type' => 'none'],
            'idempotency_key' => (string) Str::uuid(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields', ['answers.rating' => ['Select a rating between 1 and 5.']]);
    }

    public function test_the_api_reports_the_same_attribution_as_the_form_path(): void
    {
        // A captive-portal site has no validated handoff yet, so the API must not
        // claim verified attribution either.
        [$site] = $this->activeSiteWithSurvey([
            'integration_mode' => IntegrationMode::CaptivePortal,
            'integration_status' => IntegrationStatus::Active,
        ]);

        $this->getJson("/api/v1/public/sites/{$site->site_code}/survey")
            ->assertOk()
            ->assertJsonPath('data.attribution.method', $site->publicAttributionMethod()->value)
            ->assertJsonPath('data.attribution.verified_wifi_session', false);
    }

    public function test_health_reports_availability_without_secrets(): void
    {
        $this->getJson('/api/v1/public/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonMissingPath('database.host');
    }

    public function test_a_mandatory_policy_is_downgraded_when_the_gateway_is_unverified(): void
    {
        [$site, $survey] = $this->activeSiteWithSurvey([
            'integration_mode' => IntegrationMode::QrCode,
            'integration_status' => IntegrationStatus::Active,
        ]);

        SurveyAssignment::where('survey_id', $survey->id)->update([
            'policy' => AssignmentPolicy::RequiredBeforeAccess,
        ]);

        $resolved = app(ResolveSurvey::class)->forSite($site->fresh());

        $this->assertSame(AssignmentPolicy::Optional, $resolved->policy);
        $this->assertFalse($resolved->policyEnforced);
        $this->assertFalse($resolved->requiresSubmissionBeforeAccess());
    }
}
