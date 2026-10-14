<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationState;
use App\Models\Integration;
use App\Models\PortalSession;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Does the captive portal actually forward the user into our app?"
 *
 * PortalContractTest proves the contract is correct. This file proves the
 * handoff is *reachable*: the gateway is given a URL a phone can open, it
 * redirects the browser there, and the browser lands on a survey it can
 * complete without a login, without the integration secret and without
 * JavaScript.
 *
 * The failure mode this guards against is the quiet one: every contract test
 * passes while the URL handed to the gateway points at a host the captive
 * device cannot resolve — so the phone sits on a spinner and the platform
 * looks like it "does not work".
 */
class PortalForwardingTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function init(Site $site, Integration $integration, array $extra = []): array
    {
        return $this->postJson(
            '/api/v1/integrations/portal/init',
            ['site_code' => $site->site_code] + $extra,
            $this->headers($integration),
        )->json('data');
    }

    /**
     * The origin the public survey must live on: the address a phone inside the
     * walled garden can actually reach, not whatever host the caller used.
     */
    private function publicOrigin(): string
    {
        return rtrim($this->originOf((string) config('app.url')), '/');
    }

    private function originOf(string $url): string
    {
        $parts = parse_url($url);

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Pull the form action out of the rendered survey, the way a browser would.
     */
    private function formAction(string $html): string
    {
        $this->assertMatchesRegularExpression('/<form[^>]+method="POST"/i', $html);

        preg_match('/<form[^>]+action="([^"]+)"/i', $html, $matches);

        return html_entity_decode($matches[1]);
    }

    // -----------------------------------------------------------------------
    // The URL the gateway is told to send the browser to
    // -----------------------------------------------------------------------

    public function test_the_gateway_is_given_an_absolute_url_on_our_public_host(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $data = $this->init($site, $integration);

        $parts = parse_url($data['survey_url']);

        // A gateway redirects a browser to this value verbatim, so a relative
        // URL would be resolved against the gateway's own host, not ours.
        $this->assertNotEmpty($parts['scheme'] ?? null, 'The handoff URL must be absolute.');
        $this->assertSame($this->publicOrigin(), $this->originOf($data['survey_url']));
        $this->assertSame('/portal/session/'.$data['session_reference'], $parts['path']);

        parse_str((string) ($parts['query'] ?? ''), $query);
        $this->assertArrayHasKey('token', $query);
    }

    public function test_the_handoff_url_does_not_trust_the_host_the_gateway_called_us_on(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        // A gateway often reaches the platform over an internal address
        // (management VLAN, private DNS, the VPS's own IP). The phone cannot
        // resolve any of those, so the URL must not be derived from them.
        $data = $this->postJson(
            'http://gateway-internal.invalid/api/v1/integrations/portal/init',
            ['site_code' => $site->site_code],
            $this->headers($integration),
        )->json('data');

        $this->assertSame($this->publicOrigin(), $this->originOf($data['survey_url']));
        $this->assertNotSame('gateway-internal.invalid', parse_url($data['survey_url'], PHP_URL_HOST));
    }

    public function test_the_handoff_url_never_carries_the_integration_secret(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $data = $this->init($site, $integration);

        $this->assertStringNotContainsString($integration->secret, $data['survey_url']);
        $this->assertStringNotContainsString($integration->public_identifier, $data['survey_url']);
    }

    // -----------------------------------------------------------------------
    // The redirect hop itself
    // -----------------------------------------------------------------------

    public function test_the_gateway_redirects_the_browser_into_our_app(): void
    {
        config(['portal.simulator_enabled' => true]);

        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/simulator/connect', [
                'integration_id' => $integration->id,
                'site_code' => $site->site_code,
            ]);

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');

        // The gateway's redirect target must be us, on the public host — this
        // is the hop that used to send the browser to http://localhost.
        $this->assertSame($this->publicOrigin(), $this->originOf($location));
        $this->assertStringStartsWith($this->publicOrigin().'/portal/session/', $location);

        $session = PortalSession::sole();
        $this->assertSame($session->id, basename((string) parse_url($location, PHP_URL_PATH)));

        // And following it lands on our survey.
        $this->get($location)->assertOk()->assertSee('Submit and continue');
    }

    public function test_the_forwarded_browser_needs_neither_a_login_nor_the_secret(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        // A phone that has just joined the Wi-Fi: no account, no cookie, no
        // credentials of any kind.
        $page = $this->get($data['survey_url']);

        $page->assertOk();
        $this->assertNull($page->headers->get('Location'), 'The public survey must not bounce anywhere.');
        $page->assertDontSee($integration->secret);
        $page->assertDontSee($integration->public_identifier);
    }

    public function test_the_forwarded_page_is_served_over_plain_http_without_hsts(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        $response = $this->get($data['survey_url']);

        $response->assertOk();
        // Portal detection happens before the device trusts any certificate, so
        // the handoff must not be forced onto HTTPS and must not be pinned by
        // HSTS.
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    // -----------------------------------------------------------------------
    // The page the browser lands on
    // -----------------------------------------------------------------------

    public function test_the_forwarded_page_submits_without_javascript_or_a_dev_server(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        $html = (string) $this->get($data['survey_url'])->getContent();

        // A captive portal is the worst place for a build step to be missing:
        // the device may have no route to the internet at all.
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString(':5173', $html);

        // The submit path must be a plain HTML form: no handler, no JS widget.
        $this->assertStringNotContainsString('onsubmit', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+type="radio"/i', $html);

        $action = $this->formAction($html);
        $this->assertSame($this->publicOrigin(), $this->originOf($action));
        $this->assertStringContainsString('token=', $action);
    }

    public function test_the_forwarded_form_posts_back_to_our_app_and_completes_the_session(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        $action = $this->formAction((string) $this->get($data['survey_url'])->getContent());

        // The form action is the second hop: if it pointed anywhere but us, the
        // response would be lost.
        $this->assertSame($this->publicOrigin(), $this->originOf($action));
        $this->assertSame($data['session_reference'], basename((string) parse_url($action, PHP_URL_PATH)));

        $this->post($action, [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.thanks', ['session' => $data['session_reference']]));

        $response = SurveyResponse::sole();
        $this->assertSame(AttributionMethod::CaptiveVerified, $response->attribution_method);
        $this->assertTrue($response->attribution_method->isVerified());
        $this->assertTrue(PortalSession::sole()->isCompleted());

        $this->get(route('portal.thanks', ['session' => $data['session_reference']]))
            ->assertOk()
            ->assertSee('Thank you');
    }

    // -----------------------------------------------------------------------
    // The gateway's own view of what happened
    // -----------------------------------------------------------------------

    public function test_the_gateway_sees_the_forwarded_browser_finish(): void
    {
        config(['portal.simulator_enabled' => true]);

        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $admin = User::factory()->superAdmin()->create();

        $location = (string) $this->actingAs($admin)->post('/simulator/connect', [
            'integration_id' => $integration->id,
            'site_code' => $site->site_code,
        ])->headers->get('Location');

        $this->post($this->formAction((string) $this->get($location)->getContent()), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $session = PortalSession::sole();

        // The gateway asks the platform, server to server, and gets a decision
        // it can act on.
        $this->actingAs($admin)->post('/simulator/status', [
            'integration_id' => $integration->id,
            'session_reference' => $session->id,
        ])->assertOk()->assertSee('ACCESS GRANTED');
    }

    // -----------------------------------------------------------------------
    // Handing the user back to the gateway
    // -----------------------------------------------------------------------

    public function test_the_gateway_can_ask_to_get_the_user_back_after_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $returnTo = 'https://gateway.example/release?client=abc123';

        $data = $this->init($site, $integration, ['redirect_url' => $returnTo]);

        $this->assertSame($returnTo, PortalSession::sole()->redirect_url);

        // The third hop: the browser is returned to the gateway, which can now
        // release the session. Without this the user is stranded on our page.
        $this->post($data['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect($returnTo);
    }

    public function test_without_a_return_url_the_user_lands_on_our_thanks_page(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        $this->assertNull(PortalSession::sole()->redirect_url);

        $this->post($data['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.thanks', ['session' => $data['session_reference']]));
    }

    public function test_an_unsafe_return_url_is_refused_before_a_session_exists(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);

        // A return URL is echoed back to the browser, so it must not be a
        // scripting or protocol-relative redirect.
        foreach (['javascript:alert(1)', '//evil.example/phish', 'data:text/html,<h1>x'] as $bad) {
            $this->postJson('/api/v1/integrations/portal/init', [
                'site_code' => $site->site_code,
                'redirect_url' => $bad,
            ], $this->headers($integration))->assertStatus(422);
        }

        $this->assertSame(0, PortalSession::count());
    }

    public function test_a_browser_cannot_inject_its_own_return_url(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $integration = $this->integration($site->id);
        $data = $this->init($site, $integration);

        // Only the authenticated gateway may choose where the browser goes
        // next. A form field cannot override it.
        $this->post($data['survey_url'], [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
            'redirect_url' => 'https://evil.example/phish',
        ])->assertRedirect(route('portal.thanks', ['session' => $data['session_reference']]));
    }
}
