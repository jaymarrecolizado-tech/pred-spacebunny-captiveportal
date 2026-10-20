<?php

namespace Tests\Feature;

use App\Enums\IntegrationState;
use App\Enums\ScopeType;
use App\Enums\SurveyVersionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\KpiReportPage;
use App\Models\Integration;
use App\Models\Site;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\SurveyResponse;
use App\Models\SurveyVersion;
use App\Models\User;
use App\Services\Surveys\DemographicQuestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The gender and sector questions, and the "prefer to self-describe" box that
 * only counts when the option it hangs off is the one that was chosen.
 */
class SurveyDemographicsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Site, 1: Survey, 2: SurveyVersion}
     */
    private function siteWithDemographics(): array
    {
        return $this->activeSiteWithSurvey([], array_merge(
            $this->defaultQuestions(),
            DemographicQuestions::questions(),
        ));
    }

    public function test_the_form_offers_every_gender_and_sector_option(): void
    {
        [$site] = $this->siteWithDemographics();

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('Male')
            ->assertSee('Female')
            ->assertSee('Non-binary')
            ->assertSee('Prefer to self-describe')
            ->assertSee('Prefer not to say')
            ->assertSee('Government')
            ->assertSee('Private Sector')
            ->assertSee('Self-Employed')
            ->assertSee('Farmer / Fisherfolk')
            ->assertSee('Senior Citizen')
            ->assertSee('PWD (Persons with Disabilities)')
            ->assertSee('Other');
    }

    public function test_the_self_describe_box_is_revealed_without_javascript(): void
    {
        [$site] = $this->siteWithDemographics();

        $html = (string) $this->get(route('site.survey', ['siteCode' => $site->site_code]))->getContent();

        // CSS only. A captive portal phone can be slow or old, and a box that
        // needs JavaScript to appear is a box that quietly loses answers.
        $this->assertStringContainsString(
            'input[name="answers[gender]"][value="self_describe"]:checked',
            $html,
        );
    }

    public function test_a_self_description_is_only_stored_when_that_option_was_chosen(): void
    {
        [$site] = $this->siteWithDemographics();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 4,
                'issue_type' => 'none',
                'gender' => 'male',
                'gender_detail' => 'Agender',
                'sector' => 'student',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();
        $this->assertSame('male', $stored->answers_json['gender']);
        $this->assertSame('student', $stored->answers_json['sector']);
        $this->assertArrayNotHasKey('gender_detail', $stored->answers_json);
    }

    public function test_a_self_description_is_stored_when_the_option_was_chosen(): void
    {
        [$site] = $this->siteWithDemographics();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 4,
                'issue_type' => 'none',
                'gender' => 'self_describe',
                'gender_detail' => 'Agender',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();
        $this->assertSame('self_describe', $stored->answers_json['gender']);
        $this->assertSame('Agender', $stored->answers_json['gender_detail']);
    }

    public function test_the_captive_portal_form_offers_the_same_questions(): void
    {
        [$site] = $this->siteWithDemographics();

        $integration = Integration::create([
            'supplier_id' => $site->supplier_id,
            'site_id' => $site->id,
            'integration_type' => 'portal_handoff',
            'public_identifier' => Integration::generateIdentifier(),
            'secret' => Integration::generateSecret(),
            'status' => IntegrationState::Active,
            'is_test_mode' => true,
        ]);

        $init = $this->postJson('/api/v1/integrations/portal/init', [
            'site_code' => $site->site_code,
            'gateway_session_ref' => 'mac-00-11-22',
        ], [
            'X-Integration-Id' => $integration->public_identifier,
            'X-Integration-Secret' => $integration->secret,
        ])->json('data');

        parse_str((string) parse_url($init['survey_url'], PHP_URL_QUERY), $query);

        // The portal form and the site link form render the same partial, so
        // the captive portal shows the new questions too.
        $this->get('/portal/session/'.$init['session_reference'].'?token='.$query['token'])
            ->assertOk()
            ->assertSee('Non-binary')
            ->assertSee('Farmer / Fisherfolk');
    }

    public function test_the_answers_land_in_their_own_columns_for_reporting(): void
    {
        [$site] = $this->siteWithDemographics();

        $this->post(route('site.survey.store', ['siteCode' => $site->site_code]), [
            'answers' => [
                'rating' => 4,
                'issue_type' => 'none',
                'gender' => 'female',
                'sector' => 'student',
            ],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $stored = SurveyResponse::sole();

        // A column of its own, so the report can group by it without reading
        // the answers blob.
        $this->assertSame('female', $stored->gender);
        $this->assertSame('student', $stored->sector);
    }

    public function test_the_kpi_page_shows_who_answered(): void
    {
        $site = Site::factory()->create();
        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => 4,
            'issue_category' => 'none',
            'gender' => 'non_binary',
            'sector' => 'farmer_fisherfolk',
        ]);

        Livewire::actingAs(User::factory()->create(['role' => UserRole::SuperAdmin, 'scope_type' => ScopeType::All]))
            ->test(KpiReportPage::class)
            ->assertOk()
            ->assertSee('Who answered')
            ->assertSee('Non-binary')
            // The label comes from the survey, not from the stored value.
            ->assertSee('Farmer / Fisherfolk')
            ->assertSee('Did not answer');
    }

    public function test_the_printable_report_shows_who_answered(): void
    {
        $site = Site::factory()->create();
        SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => 4,
            'issue_category' => 'none',
            'gender' => 'non_binary',
            'sector' => 'pwd',
        ]);

        $html = (string) $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'scope_type' => ScopeType::All,
        ]))->get(route('reports.survey'))->getContent();

        $this->assertStringContainsString('Who answered', $html);
        $this->assertStringContainsString('Non-binary', $html);
        $this->assertStringContainsString('PWD (Persons with Disabilities)', $html);
    }

    public function test_the_command_gives_the_survey_to_every_location(): void
    {
        [$site, $survey] = $this->activeSiteWithSurvey();

        // The seeder's global assignment is what makes the survey reachable.
        SurveyAssignment::query()->delete();

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertRedirect(route('site.unavailable'));

        $this->artisan('survey:assign-all')->assertSuccessful();

        $this->get(route('site.survey', ['siteCode' => $site->site_code]))
            ->assertOk()
            ->assertSee('Send feedback');

        // Running it twice must not pile up assignments.
        $this->artisan('survey:assign-all')->assertSuccessful();
        $this->assertSame(1, SurveyAssignment::query()->count());
    }

    public function test_the_command_publishes_the_questions_as_a_new_live_version(): void
    {
        [$site, $survey, $live] = $this->activeSiteWithSurvey();

        $this->artisan('survey:add-demographics')->assertSuccessful();

        $survey->refresh();
        $this->assertNotSame($live->id, $survey->published_version_id);

        $keys = array_column($survey->publishedVersion->questions(), 'key');
        $this->assertContains('gender', $keys);
        $this->assertContains('sector', $keys);

        // The version people already answered against is untouched.
        $this->assertSame(SurveyVersionStatus::Published, $live->refresh()->status);
        $this->assertSame(2, $survey->versions()->count());

        // Running it twice must not pile up versions.
        $this->artisan('survey:add-demographics')->assertSuccessful();
        $this->assertSame(2, $survey->versions()->count());
    }
}
