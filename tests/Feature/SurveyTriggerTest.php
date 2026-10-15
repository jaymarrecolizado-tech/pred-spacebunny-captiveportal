<?php

namespace Tests\Feature;

use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Services\Surveys\SurveyTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who gets stopped and asked when they connect.
 *
 * The two rules that protect the public are pinned by tests rather than left to
 * the implementation: a device that has never answered is always asked, and a
 * device with no trackable reference is always asked.
 */
class SurveyTriggerTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE = 'device-hash-a';

    private function site(SurveyTriggerMode $mode, int $days = 7): Site
    {
        return Site::factory()->create([
            'survey_trigger_mode' => $mode->value,
            'survey_trigger_days' => $days,
        ]);
    }

    private function answered(Site $site, ?string $device = self::DEVICE, int $daysAgo = 1): void
    {
        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'session_reference_hash' => $device,
            'submitted_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_always_asks_a_returning_device(): void
    {
        $site = $this->site(SurveyTriggerMode::Always);
        $this->answered($site);

        $this->assertTrue(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
    }

    public function test_interval_lets_a_recent_device_straight_back_in(): void
    {
        $site = $this->site(SurveyTriggerMode::Interval, 7);
        $this->answered($site, daysAgo: 2);

        $this->assertFalse(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
    }

    public function test_interval_asks_again_once_the_window_has_passed(): void
    {
        $site = $this->site(SurveyTriggerMode::Interval, 7);
        $this->answered($site, daysAgo: 8);

        $this->assertTrue(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
    }

    public function test_the_window_is_configurable_per_site(): void
    {
        $site = $this->site(SurveyTriggerMode::Interval, 30);
        $this->answered($site, daysAgo: 8);

        // Eight days is past a 7 day window but well inside a 30 day one.
        $this->assertFalse(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
    }

    public function test_a_device_that_has_never_answered_is_always_asked(): void
    {
        // The rule that stops "every 7 days" from quietly excluding people who
        // only connect once a month.
        foreach ([SurveyTriggerMode::Interval, SurveyTriggerMode::Always] as $mode) {
            $site = $this->site($mode, 7);

            $this->assertTrue(
                app(SurveyTrigger::class)->shouldAsk($site, 'a-brand-new-device'),
                'a first-timer must be asked under '.$mode->value,
            );
        }
    }

    public function test_never_switches_a_location_off_even_for_first_timers(): void
    {
        $site = $this->site(SurveyTriggerMode::Never);

        $this->assertFalse(app(SurveyTrigger::class)->shouldAsk($site, 'never-seen-before'));
        $this->assertFalse(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
        $this->assertFalse(app(SurveyTrigger::class)->shouldAsk($site, null));
    }

    public function test_an_untrackable_device_is_asked_every_time(): void
    {
        // A QR or site link has no device reference. Asking twice is a small
        // annoyance; skipping them silently would be invisible coverage loss.
        $site = $this->site(SurveyTriggerMode::Interval, 7);

        $this->assertTrue(app(SurveyTrigger::class)->shouldAsk($site, null));
        $this->assertTrue(app(SurveyTrigger::class)->shouldAsk($site, ''));
    }

    public function test_answering_at_another_site_does_not_count_here(): void
    {
        $here = $this->site(SurveyTriggerMode::Interval, 7);
        $elsewhere = $this->site(SurveyTriggerMode::Interval, 7);

        $this->answered($elsewhere, daysAgo: 1);

        $this->assertTrue(
            app(SurveyTrigger::class)->shouldAsk($here, self::DEVICE),
            'a device new to this location must still be asked',
        );
    }

    public function test_a_zero_or_negative_window_is_treated_as_one_day(): void
    {
        $site = $this->site(SurveyTriggerMode::Interval, 0);
        $this->answered($site, daysAgo: 2);

        // A zero window would mean "ask every time" and must not mean "never".
        $this->assertTrue(app(SurveyTrigger::class)->shouldAsk($site, self::DEVICE));
    }
}
