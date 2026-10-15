<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-REP-007: the printable report.
 *
 * The PDF is a second route to the same data as the KPI page, which makes it a
 * scope-bypass risk. These tests pin the two things that must hold: an
 * unauthorised visitor is refused, and a supplier-scoped account cannot widen
 * the document by choosing filters.
 */
class SurveyReportPrintTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'scope_type' => ScopeType::All]);
    }

    private function response(Site $site, int $rating, ?string $issue = 'none'): SurveyResponse
    {
        return SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => $rating,
            'issue_category' => $issue,
        ]);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('reports.survey'))->assertRedirect();
    }

    public function test_a_deactivated_account_is_refused(): void
    {
        // Filament's Authenticate middleware enforces canAccessPanel(); the print
        // route must not be a way around a deactivation.
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($inactive)->get(route('reports.survey'))->assertForbidden();
    }

    public function test_it_renders_the_summary_and_the_response_appendix(): void
    {
        $site = Site::factory()->create(['site_code' => 'POP-01', 'province' => 'Ilocos Norte']);
        $this->response($site, 5);
        $this->response($site, 3, 'slow');

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        $response->assertSee('Survey Report');
        $response->assertSee('Appendix');
        // Summary figures, not just the raw rows.
        $response->assertSee('Average rating');
        $response->assertSee('Positive');
        $response->assertSee('POP-01');
        // Sample size travels with the average.
        $response->assertSee('small sample');
    }

    public function test_the_print_stylesheet_repeats_headers_and_avoids_split_rows(): void
    {
        $this->response(Site::factory()->create(), 4);

        $html = $this->actingAs($this->admin())->get(route('reports.survey'))->getContent();

        // A PDF where the header only appears on page one is unusable for an
        // appendix, and a row split across a page break is unreadable.
        $this->assertStringContainsString('display: table-header-group', $html);
        $this->assertStringContainsString('break-inside: avoid', $html);
        $this->assertStringContainsString('@page', $html);
    }

    public function test_a_supplier_scoped_user_cannot_export_another_supplier(): void
    {
        $mine = Site::factory()->create(['site_code' => 'MINE-1']);
        $theirs = Site::factory()->create(['site_code' => 'THEIRS-1']);

        $this->response($mine, 5);
        $this->response($theirs, 1, 'drops');

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.survey'));

        $response->assertOk();
        $response->assertSee('MINE-1');
        $response->assertDontSee('THEIRS-1');
        // Exactly one row rendered: the other supplier's response is not merely
        // hidden, it was never selected. (Asserted on row count rather than on a
        // truncated response id — ids are UUIDv7, so their first characters are a
        // shared timestamp and collide between rows created in the same run.)
        $this->assertCount(1, $response->viewData('responses'));
        $this->assertSame(1, $response->viewData('totalMatched'));
    }

    public function test_a_province_scoped_user_cannot_widen_the_document_with_filters(): void
    {
        $inScope = Site::factory()->create(['province' => 'Ilocos Norte']);
        Site::factory()->create(['province' => 'Pangasinan']);
        $this->response($inScope, 5);
        $this->response(Site::factory()->create(['province' => 'Pangasinan']), 1, 'drops');

        $user = User::factory()->create([
            'role' => UserRole::Analyst,
            'scope_type' => ScopeType::Province,
            'scope_provinces' => 'Ilocos Norte',
        ]);

        // The filter asks for Pangasinan; scope must still refuse it, so the
        // document is empty rather than showing either province's data.
        $response = $this->actingAs($user)->get(route('reports.survey', ['province' => 'Pangasinan']));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('responses'));
        $this->assertSame(0, $response->viewData('totalMatched'));
    }

    public function test_filters_carry_through_to_the_printed_document(): void
    {
        $pangasinan = Site::factory()->create(['province' => 'Pangasinan']);
        $ilocos = Site::factory()->create(['province' => 'Ilocos Norte']);
        $this->response($pangasinan, 5);
        $this->response($ilocos, 1, 'drops');

        $response = $this->actingAs($this->admin())->get(route('reports.survey', ['province' => 'Pangasinan']));

        $response->assertOk();
        $response->assertSee('Pangasinan');
        $response->assertSee($pangasinan->site_code);
        $response->assertDontSee($ilocos->site_code);
        // The document states its own filters so a printed copy is self-describing.
        $response->assertSee('Filters:');
        $this->assertCount(1, $response->viewData('responses'));
    }

    public function test_it_shows_where_each_site_is(): void
    {
        $site = Site::factory()->create([
            'site_code' => 'POP-01',
            'municipality' => 'Laoag',
            'province' => 'Ilocos Norte',
        ]);
        $this->response($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        // Both the by-site breakdown and the response appendix carry the location.
        $this->assertSame('Laoag, Ilocos Norte', $response->viewData('bySite')[0]['location']);
        $response->assertSee('Laoag, Ilocos Norte');
    }

    public function test_a_site_without_a_municipality_still_reports_its_province(): void
    {
        $site = Site::factory()->create(['municipality' => null, 'province' => 'Ilocos Norte']);
        $this->response($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        $this->assertSame('Ilocos Norte', $response->viewData('bySite')[0]['location']);
    }

    public function test_a_site_with_no_location_at_all_does_not_break_the_report(): void
    {
        $site = Site::factory()->create(['municipality' => null, 'province' => null]);
        $this->response($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        $this->assertNull($response->viewData('bySite')[0]['location']);
        $response->assertSee($site->site_code);
    }

    public function test_the_appendix_is_capped_and_says_so_rather_than_truncating_silently(): void
    {
        config(['reporting.pdf_appendix_max_rows' => 10]);

        $site = Site::factory()->create();
        SurveyResponse::factory(25)->create([
            'site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'overall_rating' => 4,
        ]);

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        $this->assertSame(25, $response->viewData('totalMatched'));
        $this->assertTrue($response->viewData('truncated'));
        $this->assertCount(10, $response->viewData('responses'));

        // A partial appendix that looks complete would be worse than no appendix.
        $response->assertSee('capped so the document stays printable');
        $response->assertSee('CSV export');
    }

    public function test_a_small_result_set_is_not_marked_truncated(): void
    {
        $site = Site::factory()->create();
        $this->response($site, 5);

        $response = $this->actingAs($this->admin())->get(route('reports.survey'));

        $response->assertOk();
        $this->assertFalse($response->viewData('truncated'));
        $response->assertDontSee('capped so the document stays printable');
    }
}
