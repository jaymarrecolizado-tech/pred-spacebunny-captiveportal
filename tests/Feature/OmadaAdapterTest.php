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

    public function test_a_valid_handoff_opens_the_survey(): void
    {
        [$site] = $this->activeSiteWithSurvey();

        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query($this->handoff()))
            ->assertOk()
            ->assertSee($site->name)
            ->assertSee('★', escape: false);
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
}
