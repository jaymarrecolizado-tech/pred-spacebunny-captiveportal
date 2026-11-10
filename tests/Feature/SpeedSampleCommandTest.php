<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SpeedClientSample;
use App\Models\SpeedSiteSample;
use App\Models\Supplier;
use App\Services\Omada\OmadaSpeedMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The first sample can only be a baseline. The second is where the command
 * proves it does not mistake new or reset clients for traffic.
 */
class SpeedSampleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_a_conservative_delta_between_samples(): void
    {
        $supplier = Supplier::factory()->create();
        $site = Site::factory()->create([
            'supplier_id' => $supplier->id,
            'site_code' => 'TEST-SITE',
        ]);

        $snapshot = [[
            'site_code' => 'TEST-SITE',
            'site_name' => $site->name,
            'province' => 'Cagayan',
            'clients' => [
                ['mac' => 'AA-BB-CC-00-00-01', 'name' => 'Phone', 'wireless' => true, 'trafficDown' => 1_000, 'trafficUp' => 100],
                ['mac' => 'AA-BB-CC-00-00-02', 'name' => 'Laptop', 'wireless' => false, 'trafficDown' => 500, 'trafficUp' => 50],
            ],
            'client_count' => 2,
            'wireless_count' => 1,
            'wired_count' => 1,
            'ap_to_client_max_kbps' => 150_000,
            'client_to_ap_max_kbps' => 72_000,
            'low_rate_wireless_count' => 0,
            'error' => null,
        ]];

        $mock = new class extends OmadaSpeedMonitor
        {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function refresh(): void {}

            public function snapshot(): array
            {
                return $this->rows;
            }
        };

        $mock->rows = $snapshot;
        $this->app->instance(OmadaSpeedMonitor::class, $mock);

        $this->artisan('omada:speed-sample')->assertExitCode(0);

        $first = SpeedSiteSample::where('site_id', $site->id)->sole();

        $this->assertNull($first->delta_down_bytes);
        $this->assertNull($first->down_mbps);
        $this->assertSame(0, $first->matched_clients);
        $this->assertSame(2, $first->unmatched_clients);

        // Make the baseline five minutes old so the command sees a realistic interval.
        $capturedAt = now()->subMinutes(5);
        SpeedSiteSample::where('site_id', $site->id)->update(['captured_at' => $capturedAt]);
        SpeedClientSample::where('site_id', $site->id)->update(['captured_at' => $capturedAt]);

        $mock->rows = [[
            // A new device joined this interval. Its first counter cannot be
            // differentiated from earlier traffic, so it is deliberately ignored.
            'site_code' => 'TEST-SITE',
            'site_name' => $site->name,
            'province' => 'Cagayan',
            'clients' => [
                ['mac' => 'AA-BB-CC-00-00-01', 'name' => 'Phone', 'wireless' => true, 'trafficDown' => 4_000, 'trafficUp' => 300],
                ['mac' => 'AA-BB-CC-00-00-02', 'name' => 'Laptop', 'wireless' => false, 'trafficDown' => 500, 'trafficUp' => 50],
                ['mac' => 'AA-BB-CC-00-00-03', 'name' => 'Tablet', 'wireless' => true, 'trafficDown' => 9_000, 'trafficUp' => 900],
            ],
            'client_count' => 3,
            'wireless_count' => 2,
            'wired_count' => 1,
            'ap_to_client_max_kbps' => 150_000,
            'client_to_ap_max_kbps' => 72_000,
            'low_rate_wireless_count' => 0,
            'error' => null,
        ]];

        $this->artisan('omada:speed-sample')->assertExitCode(0);

        $latest = SpeedSiteSample::where('site_id', $site->id)->latest('captured_at')->first();

        $this->assertSame(3_000, $latest->delta_down_bytes);
        $this->assertSame(200, $latest->delta_up_bytes);
        $this->assertSame(2, $latest->matched_clients);
        $this->assertSame(1, $latest->unmatched_clients);
        $this->assertGreaterThanOrEqual(298, $latest->sample_interval_seconds);
        $this->assertLessThanOrEqual(301, $latest->sample_interval_seconds);
        $this->assertNotNull($latest->down_mbps);
        $this->assertGreaterThan(0, $latest->down_mbps);
    }
}
