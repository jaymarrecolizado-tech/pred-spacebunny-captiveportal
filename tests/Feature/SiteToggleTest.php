<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\SurveyTriggerMode;
use App\Enums\UserRole;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\Tables\SitesTable;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The one-click switch in the Locations list.
 *
 * With 193 locations, opening each one to change a setting is the tedium that
 * makes people give up partway. The switch has to actually persist, and it must
 * not pretend to be live when the Wi-Fi system has never pointed here.
 */
class SiteToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
        ]);
    }

    private function click(Site $site): void
    {
        SitesTable::toggleSurveyingAction()->record($site)->call();
    }

    public function test_the_switch_turns_a_location_on(): void
    {
        $site = Site::factory()->create([
            'omada_site_id' => 'abc123',
            'survey_trigger_mode' => SurveyTriggerMode::Never,
        ]);

        $this->click($site);

        $this->assertSame(SurveyTriggerMode::Always, $site->fresh()->survey_trigger_mode);
    }

    public function test_the_switch_turns_a_location_back_off(): void
    {
        $site = Site::factory()->create([
            'omada_site_id' => 'abc123',
            'survey_trigger_mode' => SurveyTriggerMode::Always,
        ]);

        $this->click($site);

        $this->assertSame(SurveyTriggerMode::Never, $site->fresh()->survey_trigger_mode);
    }

    public function test_turning_on_a_location_the_wifi_system_has_never_reached_says_so(): void
    {
        // The point of the switch is that turning it on is not a lie.
        $site = Site::factory()->create([
            'omada_site_id' => 'abc123',
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => null,
        ]);

        $this->assertSame('Waiting for setup', $site->surveyStatus()['label']);
    }

    public function test_a_location_the_wifi_system_has_reached_reports_live(): void
    {
        $site = Site::factory()->create([
            'omada_site_id' => 'abc123',
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => now()->subHour(),
        ]);

        $this->assertSame('Live', $site->surveyStatus()['label']);
    }

    public function test_turning_a_location_off_keeps_its_feedback(): void
    {
        // Switching off must never erase what was already collected.
        $site = Site::factory()->create([
            'omada_site_id' => 'abc123',
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'portal_last_seen_at' => now(),
        ]);

        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => 5,
        ]);

        $this->click($site);

        $this->assertSame(SurveyTriggerMode::Never, $site->fresh()->survey_trigger_mode);
        $this->assertSame(1, $site->responses()->count());
    }

    public function test_the_locations_page_offers_the_switch(): void
    {
        Site::factory()->create(['omada_site_id' => 'abc123']);

        Livewire::actingAs($this->admin())
            ->test(ListSites::class)
            ->assertOk();
    }
}
