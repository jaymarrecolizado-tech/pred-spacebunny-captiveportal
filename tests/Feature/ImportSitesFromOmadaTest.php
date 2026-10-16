<?php

namespace Tests\Feature;

use App\Enums\IntegrationMode;
use App\Enums\RecordStatus;
use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use App\Services\Omada\ImportSitesFromOmada;
use App\Services\Omada\OmadaOpenApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Importing the controller's site list.
 *
 * The rule that matters: this is an additive sync, never a destructive one. A
 * bulk import that overwrote a site code, a supplier or a survey setting would
 * silently undo decisions DICT made by hand.
 */
class ImportSitesFromOmadaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array{siteId: string, name: string}>  $sites
     */
    private function importerReturning(array $sites): ImportSitesFromOmada
    {
        $client = Mockery::mock(OmadaOpenApiClient::class);
        $client->shouldReceive('sites')->andReturn($sites);

        return new ImportSitesFromOmada($client);
    }

    public function test_it_creates_a_registry_site_for_each_controller_site(): void
    {
        $result = $this->importerReturning([
            ['siteId' => 'abc123000000000000000001', 'name' => 'Piat Municipal Hall'],
            ['siteId' => 'abc123000000000000000002', 'name' => 'CSU Laoag'],
        ])->run();

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('sites', ['name' => 'Piat Municipal Hall', 'omada_site_id' => 'abc123000000000000000001']);
    }

    public function test_every_imported_site_is_switched_off(): void
    {
        // A bulk import must never start interrupting the public on its own.
        $this->importerReturning([['siteId' => 'abc1', 'name' => 'Somewhere ES']])->run();

        $site = Site::query()->where('name', 'Somewhere ES')->firstOrFail();

        $this->assertSame(SurveyTriggerMode::Never, $site->survey_trigger_mode);
        $this->assertSame(7, $site->survey_trigger_days);
    }

    public function test_an_imported_site_never_claims_a_verified_integration(): void
    {
        $this->importerReturning([['siteId' => 'abc1', 'name' => 'Somewhere ES']])->run();

        $site = Site::query()->where('name', 'Somewhere ES')->firstOrFail();

        // These locations exist on the controller, but none of them are
        // confirmed to redirect to this platform yet.
        $this->assertSame('qr_code', $site->integration_mode->value);
        $this->assertSame('draft', $site->integration_status->value);
    }

    public function test_it_links_a_hand_registered_site_without_overwriting_it(): void
    {
        // The pilot site was typed in by hand as "POP-TEST"; the controller
        // calls it "POP Test".
        $existing = Site::factory()->create([
            'site_code' => 'POP-TEST',
            'name' => 'POP Test',
            'omada_site_id' => null,
            'survey_trigger_mode' => SurveyTriggerMode::Always,
            'integration_mode' => IntegrationMode::CaptivePortal,
            'status' => RecordStatus::Active,
        ]);

        $result = $this->importerReturning([
            ['siteId' => 'zzz999000000000000000001', 'name' => 'POP Test'],
            ['siteId' => 'zzz999000000000000000002', 'name' => 'Brand New Site'],
        ])->run();

        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['created']);

        $fresh = $existing->fresh();

        $this->assertSame('zzz999000000000000000001', $fresh->omada_site_id);
        // Everything a person decided survives the import untouched.
        $this->assertSame('POP-TEST', $fresh->site_code);
        $this->assertSame(SurveyTriggerMode::Always, $fresh->survey_trigger_mode);
        $this->assertSame(IntegrationMode::CaptivePortal, $fresh->integration_mode);
    }

    public function test_re_running_the_import_does_not_duplicate_sites(): void
    {
        $rows = [['siteId' => 'abc1', 'name' => 'Piat Municipal Hall']];

        $this->importerReturning($rows)->run();
        $second = $this->importerReturning($rows)->run();

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['linked']);
        $this->assertSame(1, Site::query()->count());
    }

    public function test_two_locations_with_the_same_name_still_get_distinct_codes(): void
    {
        $this->importerReturning([
            ['siteId' => 'aaa1', 'name' => 'Piat Municipal Hall'],
            ['siteId' => 'bbb1', 'name' => 'Piat Municipal Hall'],
        ])->run();

        $codes = Site::query()->orderBy('site_code')->pluck('site_code')->all();

        $this->assertCount(2, $codes);
        $this->assertSame($codes, array_unique($codes), 'site codes must never collide');
    }

    public function test_a_row_without_an_id_is_skipped_rather_than_creating_a_broken_site(): void
    {
        $result = $this->importerReturning([
            ['siteId' => '', 'name' => 'Broken'],
            ['siteId' => 'ok1', 'name' => 'Fine'],
        ])->run();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('sites', ['name' => 'Fine']);
    }
}
