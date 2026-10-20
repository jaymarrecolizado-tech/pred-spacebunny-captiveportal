<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\SurveyTriggerMode;
use App\Enums\UserRole;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Reporting\ReportDocument;
use App\Services\Reporting\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The downloadable report formats.
 *
 * Two things must hold for every format: the summary comes before the
 * supporting data, and a scoped user cannot pull another supplier's rows through
 * the download link (FR-REP-005, FR-REP-007).
 */
class ReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'scope_type' => ScopeType::All]);
    }

    private function addResponse(Site $site, int $rating, ?string $issue = 'none'): SurveyResponse
    {
        return SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => $rating,
            'issue_category' => $issue,
            'session_reference_hash' => hash('sha256', 'AA-BB-CC-DD-EE-01'),
        ]);
    }

    public function test_a_guest_cannot_download_the_report(): void
    {
        $this->get(route('reports.survey.download', ['format' => 'csv']))->assertRedirect();
    }

    public function test_a_deactivated_account_is_refused(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($inactive)
            ->get(route('reports.survey.download', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_csv_puts_the_summary_before_the_supporting_data(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01']);
        $this->addResponse($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey.download', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment; filename="dict-survey-report-all-all.csv"',
            (string) $response->headers->get('content-disposition'),
        );

        $body = $response->getContent();

        $summaryAt = strpos($body, 'SUMMARY');
        $dataAt = strpos($body, 'RESPONSES (supporting data)');

        $this->assertNotFalse($summaryAt, 'CSV must carry a summary section');
        $this->assertNotFalse($dataAt, 'CSV must carry the supporting data section');
        $this->assertLessThan($dataAt, $summaryAt, 'the summary must come first');

        // Headline figures and the caveat that travels with them.
        $this->assertStringContainsString('Average rating', $body);
        $this->assertStringContainsString('Distinct devices', $body);
        $this->assertStringContainsString('Devices, not people', $body);
    }

    public function test_xml_is_well_formed_and_leads_with_the_summary(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01']);
        $this->addResponse($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey.download', ['format' => 'xml']));

        $response->assertOk();

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml, 'XML export must parse');

        $this->assertSame('surveyReport', $xml->getName());
        $this->assertNotNull($xml->summary);
        $this->assertNotNull($xml->responses);
        $this->assertGreaterThan(0, $xml->responses->attributes()->totalMatched);

        // Summary element precedes the response rows in document order.
        $names = array_keys(get_object_vars($xml));
        $this->assertLessThan(
            array_search('responses', $names, true),
            array_search('summary', $names, true),
        );
    }

    public function test_xml_escapes_content_that_would_otherwise_break_the_document(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01', 'name' => 'Site & Co <test>']);
        $this->addResponse($site, 4);

        $body = $this->actingAs($this->admin())
            ->get(route('reports.survey.download', ['format' => 'xml']))
            ->getContent();

        $this->assertNotFalse(simplexml_load_string((string) $body));
        $this->assertStringContainsString('&amp;', (string) $body);
    }

    public function test_a_measure_with_no_data_is_empty_rather_than_zero(): void
    {
        // A reader must be able to tell "no data" from "zero".
        $xml = simplexml_load_string(
            (string) $this->actingAs($this->admin())
                ->get(route('reports.survey.download', ['format' => 'xml']))
                ->getContent(),
        );

        $values = [];

        foreach ($xml->summary->measure as $measure) {
            $values[(string) $measure['name']] = trim((string) $measure->value);
        }

        $this->assertSame('', $values['Average rating'] ?? 'missing');
        $this->assertSame('0', $values['Total responses']);
    }

    public function test_the_spreadsheet_is_a_real_xlsx_with_summary_and_response_sheets(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01']);
        $this->addResponse($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey.download', ['format' => 'xlsx']));

        $response->assertOk();

        $body = (string) $response->getContent();

        // XLSX is a zip archive; PK is its magic number.
        $this->assertStringStartsWith('PK', $body);

        $path = tempnam(sys_get_temp_dir(), 'rep').'.xlsx';
        file_put_contents($path, $body);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'file must open as a workbook');

        $sheetNames = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('/xl\/worksheets\/sheet(\d+)\.xml$/', $zip->getNameIndex($i), $m)) {
                $sheetNames[(int) $m[1]] = true;
            }
        }

        $zip->close();
        @unlink($path);

        // Summary first, then comments, then the supporting rows.
        $this->assertCount(3, $sheetNames);
        $this->assertArrayHasKey(1, $sheetNames);
        $this->assertArrayHasKey(2, $sheetNames);
        $this->assertArrayHasKey(3, $sheetNames);
    }

    public function test_the_rating_split_and_the_comments_people_wrote_reach_every_format(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01']);

        $this->addResponse($site, 5)->update(['answers_json' => ['rating' => 5, 'comment' => 'Signal is strong here.']]);
        $this->addResponse($site, 1)->update(['answers_json' => ['rating' => 1, 'issue_type' => 'drops']]);

        $admin = $this->admin();

        // Only the box that was actually filled in comes out. The star rating and
        // the dropdown answer are strings too, and finding comments by type is
        // what stops them being reported as something the public wrote.
        $comments = app(ReportDocument::class)
            ->build($admin, ReportFilters::fromArray([], 4))['comments'];

        $this->assertCount(1, $comments);
        $this->assertSame('Signal is strong here.', $comments[0]['comment']);
        $this->assertSame('Anything else?', $comments[0]['question']);

        foreach (['csv' => '5 star', 'xml' => 'stars="5" count="1"'] as $format => $needle) {
            $body = (string) $this->actingAs($admin)
                ->get(route('reports.survey.download', ['format' => $format]))
                ->getContent();

            $this->assertStringContainsString('Signal is strong here.', $body, "{$format} must carry the comment");
            $this->assertStringContainsString($needle, $body, "{$format} must carry the rating split");
        }
    }

    public function test_the_summary_page_carries_charts_not_just_numbers(): void
    {
        Site::factory()->create(['site_code' => 'POP-01']);
        $this->addResponse(Site::first(), 4);

        $html = (string) $this->actingAs($this->admin())->get(route('reports.survey'))->getContent();

        // Inline SVG and CSS bars only: no chart library, and nothing to fetch
        // when the page is printed to PDF.
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_an_unknown_format_falls_back_to_csv_instead_of_erroring(): void
    {
        // The download is a link; a stale bookmark should still produce a file.
        $response = $this->actingAs($this->admin())
            ->get(route('reports.survey.download', ['format' => 'docx']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_filters_are_honoured_in_every_format(): void
    {
        Site::factory()->create(['province' => 'Pangasinan']);
        $ilocos = Site::factory()->create(['province' => 'Ilocos Norte']);
        $this->addResponse($ilocos, 5);

        foreach (['csv', 'xml'] as $format) {
            $body = (string) $this->actingAs($this->admin())
                ->get(route('reports.survey.download', ['format' => $format, 'province' => 'Pangasinan']))
                ->getContent();

            $this->assertStringContainsString('Pangasinan', $body, "{$format} must record the active filter");
            $this->assertStringNotContainsString('Ilocos Norte', $body, "{$format} must exclude the other province");
        }
    }

    public function test_a_supplier_scoped_user_cannot_download_another_suppliers_data(): void
    {
        $mine = Site::factory()->create(['site_code' => 'MINE-1']);
        $theirs = Site::factory()->create(['site_code' => 'THEIRS-1']);
        $this->addResponse($mine, 5);
        $this->addResponse($theirs, 1);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        foreach (['csv', 'xml'] as $format) {
            $body = (string) $this->actingAs($user)
                ->get(route('reports.survey.download', ['format' => $format]))
                ->getContent();

            $this->assertStringContainsString('MINE-1', $body, "{$format} must include their own site");
            $this->assertStringNotContainsString('THEIRS-1', $body, "{$format} leaked another supplier's site");
        }
    }

    public function test_a_site_switched_off_from_surveying_still_reports_its_history(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01']);
        $site->forceFill(['survey_trigger_mode' => SurveyTriggerMode::Never])->save();

        $this->addResponse($site, 5);

        $body = (string) $this->actingAs($this->admin())
            ->get(route('reports.survey.download', ['format' => 'csv']))
            ->getContent();

        // Switching a location off must not erase what it already collected.
        $this->assertStringContainsString('POP-01', $body);
    }
}
