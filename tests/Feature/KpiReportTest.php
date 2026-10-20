<?php

namespace Tests\Feature;

use App\Enums\AttributionMethod;
use App\Enums\IntegrationMode;
use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\Site;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Reporting\KpiReport;
use App\Services\Reporting\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec section 13 metric definitions. The assertions here are the contract:
 * averages always carry their sample size, and scope always applies.
 */
class KpiReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'scope_type' => ScopeType::All]);
    }

    private function response(Site $site, int $rating, ?string $issue, AttributionMethod $method = AttributionMethod::QrCode): SurveyResponse
    {
        return SurveyResponse::factory()->create([
            'site_id' => $site->id,
            'supplier_id' => $site->supplier_id,
            'overall_rating' => $rating,
            'issue_category' => $issue,
            'attribution_method' => $method,
        ]);
    }

    public function test_totals_average_and_sample_size_are_reported_together(): void
    {
        $site = Site::factory()->create();
        $this->response($site, 5, 'none');
        $this->response($site, 4, 'slow');
        $this->response($site, 1, 'drops');

        $report = app(KpiReport::class)->for($this->admin(), new ReportFilters);

        $this->assertSame(3, $report['totals']['responses']);
        $this->assertSame(3, $report['totals']['rated']);
        $this->assertSame(3.33, $report['totals']['average_rating']);
        // Threshold is 4: 5 and 4 qualify.
        $this->assertSame(2, $report['totals']['positive_responses']);
        $this->assertSame(66.7, $report['totals']['positive_rate']);
    }

    public function test_unrated_responses_are_excluded_from_rates_but_counted_totally(): void
    {
        $site = Site::factory()->create();
        $this->response($site, 5, 'none');
        SurveyResponse::factory()->create([
            'site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'overall_rating' => null, 'issue_category' => null,
        ]);

        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        $this->assertSame(2, $totals['responses']);
        $this->assertSame(1, $totals['rated']);
        $this->assertSame(100.0, $totals['positive_rate']);
    }

    public function test_rates_are_null_rather_than_zero_when_there_is_no_data(): void
    {
        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        // A 0% positive rate would be a lie: there are no ratings.
        $this->assertNull($totals['average_rating']);
        $this->assertNull($totals['positive_rate']);
        $this->assertSame(0, $totals['responses']);
    }

    public function test_connection_issue_rate_excludes_no_problem(): void
    {
        $site = Site::factory()->create();
        $this->response($site, 5, 'none');
        $this->response($site, 5, 'none');
        $this->response($site, 2, 'slow');
        $this->response($site, 1, 'drops');

        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        $this->assertSame(2, $totals['issues_reported']);
        $this->assertSame(50.0, $totals['issue_rate']);
    }

    public function test_verified_and_unverified_are_reported_separately(): void
    {
        $site = Site::factory()->create(['integration_mode' => IntegrationMode::CaptivePortal]);
        $this->response($site, 4, 'none', AttributionMethod::CaptiveVerified);
        $this->response($site, 5, 'none', AttributionMethod::CaptiveVerified);
        $this->response($site, 3, 'slow');

        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        $this->assertSame(2, $totals['verified']);
        $this->assertSame(1, $totals['unverified']);
    }

    public function test_small_samples_are_flagged_rather_than_presented_as_meaningful(): void
    {
        config(['reporting.minimum_sample_size' => 10]);
        $site = Site::factory()->create();
        $this->response($site, 5, 'none');

        $bySite = app(KpiReport::class)->for($this->admin(), new ReportFilters)['by_site'];

        $this->assertSame(1, $bySite[0]['rated']);
        $this->assertFalse($bySite[0]['significant']);
        // The average is still returned, but never without its sample size.
        $this->assertNotNull($bySite[0]['average_rating']);
        $this->assertSame(1, $bySite[0]['rated']);
    }

    public function test_filters_narrow_the_result_set(): void
    {
        $siteA = Site::factory()->create(['province' => 'Ilocos Norte']);
        $siteB = Site::factory()->create(['province' => 'Pangasinan']);
        $this->response($siteA, 5, 'none');
        $this->response($siteB, 1, 'slow');

        $admin = $this->admin();

        $this->assertSame(2, app(KpiReport::class)->for($admin, new ReportFilters)['totals']['responses']);
        $this->assertSame(1, app(KpiReport::class)->for($admin, new ReportFilters(province: 'Pangasinan'))['totals']['responses']);
        $this->assertSame(1, app(KpiReport::class)->for($admin, new ReportFilters(siteId: $siteA->id))['totals']['responses']);
    }

    public function test_a_supplier_scoped_user_only_reports_their_own_data(): void
    {
        $mine = Site::factory()->create();
        $theirs = Site::factory()->create();
        $this->response($mine, 5, 'none');
        $this->response($theirs, 1, 'slow');

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $totals = app(KpiReport::class)->for($user, new ReportFilters)['totals'];

        $this->assertSame(1, $totals['responses']);
        $this->assertSame(5.0, $totals['average_rating']);
    }

    public function test_a_province_scoped_user_cannot_widen_with_filters(): void
    {
        $inScope = Site::factory()->create(['province' => 'Ilocos Norte']);
        $outOfScope = Site::factory()->create(['province' => 'Pangasinan']);
        $this->response($inScope, 5, 'none');
        $this->response($outOfScope, 1, 'slow');

        $user = User::factory()->create([
            'role' => UserRole::Analyst,
            'scope_type' => ScopeType::Province,
            'scope_provinces' => 'Ilocos Norte',
        ]);

        // The filter asks for Pangasinan; scope still refuses it.
        $totals = app(KpiReport::class)->for($user, new ReportFilters(province: 'Pangasinan'))['totals'];

        $this->assertSame(0, $totals['responses']);
    }

    public function test_distinct_devices_counts_hashed_macs_and_is_labelled_as_devices(): void
    {
        $site = Site::factory()->create();

        // The controller stores hash('sha256', $clientMac); two submissions from
        // the same device must collapse to one, two devices must stay two.
        $macA = hash('sha256', 'AA:BB:CC:DD:EE:01');
        $macB = hash('sha256', 'AA:BB:CC:DD:EE:02');

        SurveyResponse::factory()->create(['site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'session_reference_hash' => $macA]);
        SurveyResponse::factory()->create(['site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'session_reference_hash' => $macA]);
        SurveyResponse::factory()->create(['site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'session_reference_hash' => $macB]);
        // A QR / site-link respondent has no device address at all.
        SurveyResponse::factory()->create(['site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'session_reference_hash' => null]);

        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        $this->assertSame(2, $totals['distinct_devices']);
        $this->assertSame(3, $totals['device_responses']);
        // The QR response is counted as a response but never as a device.
        $this->assertSame(4, $totals['responses']);
    }

    public function test_distinct_devices_is_zero_rather_than_null_when_nobody_used_the_portal(): void
    {
        $site = Site::factory()->create();
        SurveyResponse::factory()->create([
            'site_id' => $site->id, 'supplier_id' => $site->supplier_id, 'session_reference_hash' => null,
        ]);

        $totals = app(KpiReport::class)->for($this->admin(), new ReportFilters)['totals'];

        $this->assertSame(0, $totals['distinct_devices']);
        $this->assertSame(0, $totals['device_responses']);
    }

    public function test_device_counts_respect_scope(): void
    {
        $mine = Site::factory()->create();
        $theirs = Site::factory()->create();

        SurveyResponse::factory()->create([
            'site_id' => $mine->id, 'supplier_id' => $mine->supplier_id,
            'session_reference_hash' => hash('sha256', 'AA:BB:CC:DD:EE:01'),
        ]);
        SurveyResponse::factory()->create([
            'site_id' => $theirs->id, 'supplier_id' => $theirs->supplier_id,
            'session_reference_hash' => hash('sha256', 'AA:BB:CC:DD:EE:02'),
        ]);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $totals = app(KpiReport::class)->for($user, new ReportFilters)['totals'];

        // Otherwise the device count would leak another supplier's device count.
        $this->assertSame(1, $totals['distinct_devices']);
    }

    /**
     * Every breakdown must count the same rows the totals count.
     *
     * Regression guard: the breakdowns shared one query builder, and the first
     * one to add a GROUP BY to it quietly changed what the next ones counted.
     * The report showed per-site counts in the "issues" and "rating" charts and
     * nobody noticed, because the numbers looked plausible.
     */
    public function test_every_breakdown_counts_the_same_rows_the_totals_count(): void
    {
        $site = Site::factory()->create();
        $other = Site::factory()->create();

        $this->response($site, 5, 'none');
        $this->response($site, 4, 'slow');
        $this->response($site, 4, 'none');
        $this->response($other, 2, 'slow');

        $report = app(KpiReport::class)->for($this->admin(), new ReportFilters);

        $this->assertSame(4, $report['totals']['responses']);

        $issues = collect($report['by_issue'])->keyBy('issue_category');
        $this->assertSame(2, $issues['none']['total']);
        $this->assertSame(2, $issues['slow']['total']);

        $ratings = collect($report['rating_distribution'])->keyBy('stars');
        $this->assertSame(1, $ratings[5]['total']);
        $this->assertSame(2, $ratings[4]['total']);
        $this->assertSame(1, $ratings[2]['total']);

        $sites = collect($report['by_site'])->keyBy('label');
        $this->assertSame(3, $sites[$site->site_code]['responses']);
        $this->assertSame(1, $sites[$other->site_code]['responses']);

        $this->assertSame(4, array_sum(array_column($report['daily'], 'responses')));
    }

    public function test_who_answered_is_broken_down_and_the_people_who_skipped_are_counted(): void
    {
        $site = Site::factory()->create();

        $this->response($site, 5, 'none')->update(['gender' => 'female', 'sector' => 'student']);
        $this->response($site, 4, 'none')->update(['gender' => 'female', 'sector' => 'student']);
        $this->response($site, 3, 'slow')->update(['gender' => 'male', 'sector' => 'farmer_fisherfolk']);
        // A response from before the questions were asked.
        $this->response($site, 2, 'none');

        $report = app(KpiReport::class)->for($this->admin(), new ReportFilters);

        $gender = collect($report['by_gender'])->keyBy('label');

        $this->assertSame(2, $gender['Female']['total']);
        $this->assertSame(1, $gender['Male']['total']);
        // The rows come out in the order the survey asks them, not by count,
        // and every option is listed even when nobody chose it.
        $this->assertSame(
            ['Male', 'Female', 'Non-binary', 'Prefer to self-describe', 'Prefer not to say', 'Did not answer'],
            array_column($report['by_gender'], 'label'),
        );
        // Somebody who skipped is counted, not quietly left out: a breakdown
        // without it would read as if everybody had an opinion.
        $this->assertSame(1, $gender['Did not answer']['total']);
        $this->assertSame(25.0, $gender['Did not answer']['share']);

        $sector = collect($report['by_sector'])->keyBy('label');

        $this->assertSame(2, $sector['Student']['total']);
        // The option label comes from the survey, not from the stored value.
        $this->assertSame(1, $sector['Farmer / Fisherfolk']['total']);
        $this->assertSame(1, $sector['Did not answer']['total']);
    }

    public function test_who_answered_respects_scope(): void
    {
        $mine = Site::factory()->create();
        $theirs = Site::factory()->create();

        $this->response($mine, 5, 'none')->update(['gender' => 'female']);
        $this->response($theirs, 1, 'slow')->update(['gender' => 'male']);

        $user = User::factory()->create([
            'role' => UserRole::SupplierAdmin,
            'scope_type' => ScopeType::Supplier,
            'scope_supplier_id' => $mine->supplier_id,
        ]);

        $report = app(KpiReport::class)->for($user, new ReportFilters);

        $gender = collect($report['by_gender'])->keyBy('label');

        // Otherwise the breakdown would leak another supplier's answers.
        $this->assertSame(1, $gender['Female']['total']);
        $this->assertSame(0, $gender['Male']['total']);
        // The other supplier's response is out of scope, so it is not counted
        // here either - not even as a person who did not answer.
        $this->assertSame(0, $gender['Did not answer']['total']);
        $this->assertSame(1, $report['totals']['responses']);
    }

    public function test_filters_can_be_built_from_array_input(): void
    {
        $filters = ReportFilters::fromArray([
            'from' => '2026-10-01',
            'to' => '2026-10-14',
            'attribution' => 'captive_verified',
            'supplier_id' => '5',
        ], positiveThreshold: 4);

        $this->assertSame('2026-10-01', $filters->from->toDateString());
        $this->assertSame('2026-10-14', $filters->to->toDateString());
        $this->assertSame(AttributionMethod::CaptiveVerified, $filters->attribution);
        $this->assertSame(5, $filters->supplierId);
    }
}
