<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
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
            ->assertSee('https://e.gov.ph/', escape: false);
    }

    public function test_the_thanks_page_has_no_redirect_when_unconfigured(): void
    {
        [$site] = $this->activeSiteWithSurvey();
        config(['portal.success_url' => null]);

        $this->withSession(['authorized' => true, 'reference' => 'abc12345'])
            ->get(route('portal.omada.thanks', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('id="count"', escape: false)
            ->assertDontSee('e.gov.ph');
    }
}
