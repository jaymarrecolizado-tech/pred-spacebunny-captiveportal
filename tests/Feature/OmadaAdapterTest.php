<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
use App\Enums\SurveyTriggerMode;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Omada External Portal Server adapter — receive side only.
 *
 * The handoff parameters are unsigned, so the only honest outcome is an
 * unverified survey. Every test here guards that boundary.
 */
class OmadaAdapterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function handoff(?int $ageSeconds = 0): array
    {
        // Microseconds, per the TP-Link documentation.
        $t = (int) ((microtime(true) - ($ageSeconds ?? 0)) * 1_000_000);

        return [
            'clientMac' => 'AA-BB-CC-DD-EE-FF',
            'apMac' => '0C-EF-15-CF-3B-1A',
            'ssidName' => 'NMS Test AP',
            't' => (string) $t,
            'radioId' => '1',
            'site' => 'POP Test',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function handoffInSeconds(?int $ageSeconds = 0): array
    {
        // What the real controller actually sends (seen live in access logs).
        $params = $this->handoff($ageSeconds);
        $params['t'] = (string) (time() - ($ageSeconds ?? 0));
        $params['site'] = '68e88354bb5fdb13e8af0451';

        return $params;
    }

    /**
     * A portal bound to a network instead of an SSID.
     *
     * Captured from the real controller at Cagayan Museum: the access point
     * is called gatewayMac, the radio is called vid, and there is no SSID
     * name at all. Requiring apMac refused every one of those devices, and the
     * refusal looked like a portal that was simply not set up.
     *
     * @return array<string, string>
     */
    private function networkHandoff(): array
    {
        return [
            'clientMac' => 'CE-73-D0-4A-A1-27',
            'clientIp' => '163.227.101.130',
            'gatewayMac' => '18-69-45-7F-19-B2',
            'vid' => '1',
            'site' => '6a6734424ef8361fe4bcee45',
            'redirectUrl' => 'http://conn-service-us-04.allawnos.com/generate204',
            't' => (string) time(),
        ];
    }

    public function test_a_network_portal_handoff_is_accepted(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->networkHandoff()))
            ->assertOk()
            ->assertSee($site->name);
    }

    public function test_a_network_portal_handoff_still_records_the_device(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $params = $this->networkHandoff();
        $url = route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($params);

        $this->get($url)->assertOk();

        $this->post(route('portal.omada.store', ['siteCode' => $site->site_code]).'?'.http_build_query($params), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        // The hashed MAC is what correlates a later authorization with the
        // survey that was answered.
        $this->assertSame(
            hash('sha256', 'CE-73-D0-4A-A1-27'),
            SurveyResponse::sole()->session_reference_hash,
        );
    }

    public function test_a_handoff_with_no_access_point_at_all_is_still_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $params = $this->networkHandoff();
        unset($params['gatewayMac']);

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($params))
            ->assertRedirect(route('portal.unavailable'));
    }

    public function test_a_valid_handoff_opens_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            ->assertOk()
            ->assertSee($site->name)
            ->assertSee('★', escape: false);
    }

    public function test_a_handoff_in_seconds_opens_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoffInSeconds()))
            ->assertOk()
            ->assertSee($site->name);
    }

    public function test_a_returning_device_on_an_interval_site_gets_access_without_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $site->forceFill(['survey_trigger_mode' => 'interval', 'survey_trigger_days' => 7])->save();

        // This exact device answered yesterday.
        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'session_reference_hash' => hash('sha256', 'AA-BB-CC-DD-EE-FF'),
            'submitted_at' => now()->subDay(),
        ]);

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            // Redirected past the survey, to the thank-you page that grants access.
            ->assertRedirect(route('portal.omada.thanks', ['siteCode' => $site->site_code]));
    }

    public function test_a_first_timer_is_still_asked_even_on_an_interval_site(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $site->forceFill(['survey_trigger_mode' => 'interval', 'survey_trigger_days' => 7])->save();

        // Otherwise "every 7 days" would silently exclude anyone who only ever
        // connects once a month.
        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            ->assertOk()
            ->assertSee($site->name);
    }

    public function test_a_switched_off_site_never_shows_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $site->forceFill(['survey_trigger_mode' => 'never'])->save();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            ->assertRedirect(route('portal.omada.thanks', ['siteCode' => $site->site_code]));
    }

    public function test_the_default_is_to_ask_every_time(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->assertSame(SurveyTriggerMode::Always, $site->fresh()->survey_trigger_mode);

        // Adding the feature must not change behaviour for an unconfigured site.
        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'session_reference_hash' => hash('sha256', 'AA-BB-CC-DD-EE-FF'),
            'submitted_at' => now()->subDay(),
        ]);

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            ->assertOk()
            ->assertSee($site->name);
    }

    public function test_a_bare_url_without_handoff_parameters_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        // The adapter URL alone must grant nothing: anyone can type it.
        $this->get(route('portal.omada', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('portal.unavailable'));
    }

    public function test_a_stale_handoff_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff(3600)))
            ->assertRedirect(route('portal.unavailable'));

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoffInSeconds(3600)))
            ->assertRedirect(route('portal.unavailable'));
    }

    public function test_a_malformed_timestamp_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $params = $this->handoff();
        $params['t'] = 'not-a-time';

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($params))
            ->assertRedirect(route('portal.unavailable'));
    }

    public function test_an_unknown_site_is_refused(): void
    {
        $this->get(route('portal.omada', ['siteCode' => 'NOPE-0001']).'?'.http_build_query($this->handoff()))
            ->assertRedirect(route('portal.unavailable'));
    }

    /**
     * The three refusals need three different instructions.
     *
     * Someone who pastes the address into a browser has not done anything
     * wrong, and "this link is no longer valid" sends them looking for a
     * problem that is not there.
     */
    public function test_each_refusal_says_what_actually_went_wrong(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('portal.unavailable'))
            ->assertSessionHas('portalReason', 'no_handoff');

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff(3600)))
            ->assertRedirect(route('portal.unavailable'))
            ->assertSessionHas('portalReason', 'expired');

        $this->get(route('portal.omada', ['siteCode' => 'NOPE-0001']).'?'.http_build_query($this->handoff()))
            ->assertRedirect(route('portal.unavailable'))
            ->assertSessionHas('portalReason', 'unknown_location');
    }

    public function test_the_unavailable_page_explains_a_manual_visit_instead_of_blaming_the_link(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('portal.unavailable'));

        $this->get(route('portal.unavailable'))
            ->assertOk()
            ->assertSee('This page opens by itself when you connect')
            ->assertDontSee('This check-in link is no longer valid');
    }

    public function test_a_submission_through_the_adapter_is_unverified(): void
    {
        // Mirror the pilot: a captive-portal site with no validated handoff.
        [$site] = $this->activeSiteWithSurvey(['integration_mode' => IntegrationMode::CaptivePortal]);

        $url = route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff());
        $this->get($url)->assertOk();

        // The adapter reuses the public form, so the POST path is identical.
        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();
        $this->assertSame(AttributionMethod::SiteLink, $stored->attribution_method);
        $this->assertFalse($stored->attribution_method->isVerified());
    }

    public function test_a_submission_through_the_adapter_post_counts_down(): void
    {
        [$site] = $this->activeSiteWithSurvey(['integration_mode' => IntegrationMode::CaptivePortal]);
        $postUrl = route('portal.omada.store', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoffInSeconds());

        // No hotspot operator is configured in testing, so authorization
        // degrades gracefully instead of failing.
        $this->post($postUrl, [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.omada.thanks', ['siteCode' => $site->site_code]));

        $stored = SurveyResponse::sole();
        $this->assertSame(AttributionMethod::SiteLink, $stored->attribution_method);
        // The client MAC is kept as a hashed correlation, never raw.
        $this->assertSame(hash('sha256', 'AA-BB-CC-DD-EE-FF'), $stored->session_reference_hash);

        $this->followRedirects($this->post($postUrl, [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ]))
            // No operator configured: honest fallback, no counter, no promise.
            ->assertSee("couldn't connect you automatically", escape: false)
            ->assertDontSee('id="count"', escape: false);
    }

    public function test_a_post_without_a_handoff_is_refused(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->post(route('portal.omada.store', ['siteCode' => $site->site_code]), [
            'answers' => $this->validAnswers(),
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('portal.unavailable'));

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_a_post_with_bad_answers_is_rejected(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        $postUrl = route('portal.omada.store', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoffInSeconds());

        $this->post($postUrl, [
            'answers' => ['rating' => 99, 'issue_type' => 'none'],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('answers.rating');

        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_the_thanks_page_without_a_session_is_honest(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        // Opened directly, with no submission behind it: no counter, no claim.
        $this->get(route('portal.omada.thanks', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('Thank you')
            ->assertDontSee('id="count"', escape: false);
    }

    public function test_the_thanks_page_redirects_after_the_countdown_when_configured(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        config(['portal.success_url' => 'https://e.gov.ph/']);

        $this->withSession(['authorized' => true, 'reference' => 'abc12345'])
            ->get(route('portal.omada.thanks', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('id="count"', escape: false)
            ->assertSee('https://e.gov.ph/', escape: false)
            // Parser-level redirect as a second mechanism: some captive
            // mini-browsers swallow JS navigation but honor meta refresh.
            ->assertSee('http-equiv="refresh"', escape: false);
    }

    public function test_the_thanks_page_has_no_redirect_when_unconfigured(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        config(['portal.success_url' => null]);

        $this->withSession(['authorized' => true, 'reference' => 'abc12345'])
            ->get(route('portal.omada.thanks', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('id="count"', escape: false)
            // No auto-redirect of any kind, and no hardcoded destination.
            ->assertDontSee('http-equiv="refresh"', escape: false)
            ->assertDontSee('location.replace')
            ->assertDontSee('e.gov.ph');
    }
}
