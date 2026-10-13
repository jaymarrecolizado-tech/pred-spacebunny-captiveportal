<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationState;
use App\Enums\RecordStatus;
use App\Models\Integration;
use App\Models\PortalSession;
use App\Models\Supplier;
use App\Models\SurveyResponse;
use App\Services\Portal\PortalToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Spec 9.2-9.5, 11.2 and 12.3: the captive portal contract.
 *
 * The important assertions are the negative ones — a public browser must never
 * be able to manufacture a verified session.
 */
class PortalContractTest extends TestCase
{
    use RefreshDatabase;

    private function integration(?int $siteId = null): Integration
    {
        return Integration::create([
            'supplier_id' => Supplier::factory()->create()->id,
            'site_id' => $siteId,
            'integration_type' => 'portal_handoff',
            'public_identifier' => Integration::generateIdentifier(),
            'secret' => Integration::generateSecret(),
            'status' => IntegrationState::Active,
            'is_test_mode' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Integration $integration, ?string $secret = null): array
    {
        return [
            'X-Integration-Id' => $integration->public_identifier,
            'X-Integration-Secret' => $secret ?? $integration->secret,
        ];
    }

    public function test_a_gateway_can_open_a_session_and_get_a_survey_url(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $response = $this->postJson('/api/v1/integrations/portal/init', [
            'site_code' => $site->site_code,
            'gateway_session_ref' => 'mac-00-11-22',
        ], $this->headers($integration));

        $response->assertCreated()
            ->assertJsonPath('data.site_code', $site->site_code)
            ->assertJsonStructure(['data' => ['session_reference', 'expires_at', 'survey_url']]);

        $session = PortalSession::sole();
        $this->assertTrue($session->isUsable());

        // The gateway's reference is stored hashed, never raw.
        $this->assertSame(hash('sha256', 'mac-00-11-22'), $session->gateway_session_hash);
    }

    public function test_init_rejects_bad_credentials(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], [
            'X-Integration-Id' => $integration->public_identifier,
            'X-Integration-Secret' => 'wrong-secret',
        ])->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated_integration');

        $this->assertSame(0, PortalSession::count());
    }

    public function test_init_rejects_a_revoked_integration(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $integration->update(['revoked_at' => now()]);

        $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'integration_not_active');
    }

    public function test_an_integration_bound_to_one_site_cannot_open_another(): void
    {
        [$siteA] = $this->activeSiteWithSurvey();
        [$siteB] = $this->activeSiteWithSurvey();
        $integration = $this->integration($siteA->id);

        $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $siteB->site_code], $this->headers($integration))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'site_mismatch');

        $this->assertSame(0, PortalSession::count());
    }

    public function test_the_full_handoff_records_verified_attribution(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', [
            'site_code' => $site->site_code,
        ], $this->headers($integration))->json('data');

        $session = PortalSession::sole();

        $this->get($init['survey_url'])->assertOk()->assertSee('Submit and continue');

        $this->post($init['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.thanks', ['session' => $session->id]));

        // FR-RESP-005: this is the only path that may claim verified attribution.
        $response = SurveyResponse::sole();
        $this->assertSame(AttributionMethod::CaptiveVerified, $response->attribution_method);
        $this->assertTrue($response->attribution_method->isVerified());

        $this->assertTrue($session->fresh()->isCompleted());
    }

    public function test_the_gateway_sees_completion_and_can_verify_the_signature(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        $this->post($init['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $session = PortalSession::sole();

        $response = $this->postJson('/api/v1/integrations/portal/status', [
            'session_reference' => $session->id,
        ], $this->headers($integration));

        $response->assertOk()->assertJsonPath('data.completed', true);

        // The signature is derived from the secret, so the gateway can recheck it.
        $outcome = $response->json('data');
        $this->assertSame(
            $integration->signCompletion($session->id, $session->fresh()->completed_at->toIso8601String()),
            $outcome['signature'],
        );
    }

    public function test_a_tampered_session_reference_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        $tampered = str_replace($init['session_reference'], (string) Str::uuid(), $init['survey_url']);

        $this->get($tampered)->assertRedirect(route('portal.unavailable'));
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_garbage_token_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        // Flipping a character inside the token must break its authenticated
        // encryption. (Appending after base64 padding would not: it is ignored.)
        parse_str((string) parse_url($init['survey_url'], PHP_URL_QUERY), $query);
        $token = $query['token'];
        $flipped = substr($token, 0, 20).(substr($token, 20, 1) === 'A' ? 'B' : 'A').substr($token, 21);

        $this->get('/portal/session/'.$init['session_reference'].'?token='.$flipped)
            ->assertRedirect(route('portal.unavailable'));

        $this->get('/portal/session/'.$init['session_reference'].'?token=not-a-token')
            ->assertRedirect(route('portal.unavailable'));

        $this->get('/portal/session/'.$init['session_reference'])
            ->assertRedirect(route('portal.unavailable'));
    }

    public function test_an_expired_session_cannot_be_completed(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');
        $session = PortalSession::sole();

        $session->update(['expires_at' => now()->subMinute()]);

        $this->post($init['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.unavailable'));

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_completed_session_cannot_be_replayed(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        $this->post($init['survey_url'], ['answers' => $this->validAnswers(), 'idempotency_key' => (string) Str::uuid()]);

        // Replaying the same handoff must not create a second response.
        $this->post($init['survey_url'], ['answers' => $this->validAnswers(), 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('portal.unavailable'));

        $this->assertSame(1, SurveyResponse::count());
    }

    public function test_one_integration_cannot_read_another_integrations_session(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $mine = $this->integration($site->id);
        $theirs = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($mine))->json('data');

        $this->postJson('/api/v1/integrations/portal/status', [
            'session_reference' => $init['session_reference'],
        ], $this->headers($theirs))->assertNotFound();
    }

    public function test_the_gateway_session_reference_is_never_stored_raw(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $this->postJson('/api/v1/integrations/portal/init', [
            'site_code' => $site->site_code,
            'gateway_session_ref' => '00-11-22-33-44-55',
        ], $this->headers($integration));

        $this->assertDatabaseMissing('portal_sessions', ['gateway_session_hash' => '00-11-22-33-44-55']);
        $this->assertSame(
            hash('sha256', '00-11-22-33-44-55'),
            PortalSession::sole()->gateway_session_hash,
        );
    }

    public function test_the_secret_is_never_returned_or_serialised(): void
    {
        $integration = $this->integration();

        $this->assertArrayNotHasKey('secret', $integration->toArray());
        $this->assertStringNotContainsString($integration->secret, json_encode($integration));

        $response = $this->postJson('/api/v1/integrations/portal/init', [], $this->headers($integration));
        $this->assertStringNotContainsString($integration->secret, $response->getContent());
    }

    public function test_the_token_is_bound_to_its_session(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');
        $session = PortalSession::sole();

        // The token travels in the query string of the URL handed to the gateway.
        parse_str((string) parse_url($init['survey_url'], PHP_URL_QUERY), $query);
        $payload = PortalToken::verify($query['token'] ?? null);

        // The token's bindings must equal the stored session's.
        $this->assertNotNull($payload);
        $this->assertSame($session->id, $payload['sid']);
        $this->assertSame($session->site_id, $payload['site']);
        $this->assertSame($session->integration_id, $payload['nid']);
        $this->assertSame($session->nonce, $payload['nonce']);
    }

    public function test_a_disabled_site_stops_serving_the_portal(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        $site->update(['status' => RecordStatus::Inactive]);

        $this->post($init['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.unavailable'));
    }

    public function test_rotating_the_secret_invalidates_the_previous_one(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $oldSecret = $integration->secret;

        $integration->update(['secret' => Integration::generateSecret()]);

        $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], [
            'X-Integration-Id' => $integration->public_identifier,
            'X-Integration-Secret' => $oldSecret,
        ])->assertUnauthorized();

        $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration->fresh()))
            ->assertCreated();
    }

    public function test_completion_is_idempotent_for_gateways_that_record_it_themselves(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');

        $first = $this->postJson('/api/v1/integrations/portal/complete', [
            'session_reference' => $init['session_reference'],
        ], $this->headers($integration))->assertOk()->json('data');

        $second = $this->postJson('/api/v1/integrations/portal/complete', [
            'session_reference' => $init['session_reference'],
        ], $this->headers($integration))->assertOk()->json('data');

        $this->assertSame($first['completed_at'], $second['completed_at']);
        $this->assertTrue(PortalSession::sole()->isCompleted());
    }

    public function test_an_expired_session_is_reported_as_expired_to_the_gateway(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $init = $this->postJson('/api/v1/integrations/portal/init', ['site_code' => $site->site_code], $this->headers($integration))->json('data');
        PortalSession::sole()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/integrations/portal/status', [
            'session_reference' => $init['session_reference'],
        ], $this->headers($integration))->assertOk()->assertJsonPath('data.completed', false);
    }
}
