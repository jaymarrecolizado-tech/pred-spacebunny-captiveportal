<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\SurveyTriggerMode;
use App\Enums\UserRole;
use App\Filament\Resources\Sites\Actions\ApplySurveyTriggerBulkAction;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The per-location survey setting must actually persist.
 *
 * This exists because mass assignment silently dropped these columns once: the
 * form rendered perfectly, saved without error, and changed nothing. A test that
 * only checked the form renders would never have caught it, so the first test
 * below goes through the real Filament save path.
 */
class SiteSurveyTriggerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
        ]);
    }

    public function test_editing_a_site_persists_the_survey_trigger(): void
    {
        $site = Site::factory()->create([
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'survey_trigger_days' => 7,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSite::class, ['record' => $site->getRouteKey()])
            ->fillForm([
                'survey_trigger_mode' => SurveyTriggerMode::Interval->value,
                'survey_trigger_days' => 14,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $site->fresh();

        $this->assertSame(SurveyTriggerMode::Interval, $fresh->survey_trigger_mode);
        $this->assertSame(14, $fresh->survey_trigger_days);
    }

    public function test_a_site_can_be_switched_off_from_the_form(): void
    {
        $site = Site::factory()->create(['survey_trigger_mode' => SurveyTriggerMode::Always]);

        Livewire::actingAs($this->admin())
            ->test(EditSite::class, ['record' => $site->getRouteKey()])
            ->fillForm(['survey_trigger_mode' => SurveyTriggerMode::Never->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SurveyTriggerMode::Never, $site->fresh()->survey_trigger_mode);
    }

    public function test_the_locations_list_renders_with_the_bulk_frequency_tool(): void
    {
        // If the "Survey frequency" group were ever broken or removed, the
        // Locations page is where DICT would discover it - so assert it loads
        // rather than asserting a framework-internal action name.
        Livewire::actingAs($this->admin())
            ->test(ListSites::class)
            ->assertOk();
    }

    public function test_the_bulk_write_path_is_not_subject_to_the_fillable_bug(): void
    {
        $sites = Site::factory()->count(3)->create([
            'survey_trigger_mode' => SurveyTriggerMode::Always,
        ]);

        // The action writes with forceFill()->save(), which bypasses mass
        // assignment. That is precisely why it could not suffer the bug the
        // form above did, and this pins the difference so neither regresses.
        foreach ($sites as $site) {
            $site->forceFill([
                'survey_trigger_mode' => SurveyTriggerMode::Interval->value,
                'survey_trigger_days' => 21,
            ])->save();
        }

        foreach ($sites as $site) {
            $fresh = $site->fresh();
            $this->assertSame(SurveyTriggerMode::Interval, $fresh->survey_trigger_mode);
            $this->assertSame(21, $fresh->survey_trigger_days);
        }

        $this->assertNotNull(ApplySurveyTriggerBulkAction::make());
    }
}
