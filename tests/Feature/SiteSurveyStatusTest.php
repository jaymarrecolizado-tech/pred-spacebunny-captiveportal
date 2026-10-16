<?php

namespace Tests\Feature;

use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a location's status says, and how it learns it.
 *
 * The confusion this prevents: an operator ticks "ask for feedback", nothing
 * happens, and there is no explanation. The status has to name which of the
 * three reasons applies.
 */
class SiteSurveyStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_switched_off_location_reads_as_off(): void
    {
        $site = Site::factory()->create([
            'survey_trigger_mode' => SurveyTriggerMode::Never,
            'portal_last_seen_at' => now(),
        ]);

        $status = $site->surveyStatus();

        $this->assertSame('Off', $status['label']);
        $this->assertSame('gray', $status['tone']);
    }

    public function test_asking_before_the_controller_points_at_us_is_called_out(): void
    {
        // The exact trap: turned on, but the controller has never sent anyone.
        $site = Site::factory()->create([
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => null,
        ]);

        $status = $site->surveyStatus();

        $this->assertSame('Waiting for setup', $status['label']);
        $this->assertSame('warning', $status['tone']);
        // The hint has to say what to do, not just that something is wrong.
        $this->assertStringContainsString('Point this location', $status['hint']);
    }

    public function test_a_location_that_has_connected_reports_live(): void
    {
        $site = Site::factory()->create([
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => now()->subHour(),
        ]);

        $status = $site->surveyStatus();

        $this->assertSame('Live', $status['label']);
        $this->assertSame('success', $status['tone']);
    }

    public function test_live_status_explains_the_schedule_in_plain_words(): void
    {
        $site = Site::factory()->create([
            'survey_trigger_mode' => SurveyTriggerMode::Interval,
            'survey_trigger_days' => 14,
            'portal_last_seen_at' => now(),
        ]);

        $this->assertStringContainsString('14 days', $site->surveyStatus()['hint']);
    }

    public function test_a_handoff_marks_the_location_as_connected(): void
    {
        // The detection is what removes the need for a manual checkbox.
        $site = Site::factory()->create([
            'site_code' => 'PORTAL-SEE-01',
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => null,
        ]);

        $this->assertNull($site->portal_last_seen_at);

        // The handoff is redirected on because this bare test site has no
        // survey assigned - but the point is that it was RECORDED first, since
        // "the controller pointed at us" is independent of whether we serve a
        // survey there.
        $this->get(route('portal.omada', ['siteCode' => $site->site_code]).'?'.http_build_query([
            'clientMac' => 'AA-BB-CC-DD-EE-FF',
            'apMac' => '0C-EF-15-CF-3B-1A',
            'ssidName' => 'Test AP',
            // Cast to int first: (string) on a large float yields "1.79E+15",
            // which fails the controller's digits-only timestamp check.
            't' => (string) ((int) (microtime(true) * 1_000_000)),
            'radioId' => '1',
            'site' => $site->site_code,
        ]));

        $this->assertNotNull(
            $site->fresh()->portal_last_seen_at,
            'a portal handoff proves the controller points here',
        );
    }
}
