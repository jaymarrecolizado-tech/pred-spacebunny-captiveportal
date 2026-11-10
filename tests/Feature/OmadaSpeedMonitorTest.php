<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Supplier;
use App\Services\Omada\OmadaSpeedMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmadaSpeedMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_paginates_clients_beyond_the_first_page(): void
    {
        $supplier = Supplier::factory()->create();
        $site = Site::factory()->create([
            'supplier_id' => $supplier->id,
            'site_code' => 'PAGINATION-SITE',
            'name' => 'Pagination Site',
            'omada_site_id' => 'omada-site-001',
            'province' => 'Cagayan',
        ]);

        config([
            'omada.client_id' => 'test-client',
            'omada.client_secret' => 'test-secret',
            'omada.omadac_id' => 'test-controller',
            'omada.base_url' => 'https://omada.test/openapi',
            'omada.verify_tls' => false,
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/authorize/token')) {
                return Http::response(['errorCode' => 0, 'result' => ['accessToken' => 'test-token']], 200);
            }

            if (str_contains($request->url(), 'page=1')) {
                return Http::response([
                    'errorCode' => 0,
                    'result' => [
                        'totalRows' => 101,
                        'data' => array_map(fn (int $i): array => $this->client($i), range(1, 100)),
                    ],
                ], 200);
            }

            if (str_contains($request->url(), 'page=2')) {
                return Http::response([
                    'errorCode' => 0,
                    'result' => [
                        'totalRows' => 101,
                        'data' => [$this->client(101)],
                    ],
                ], 200);
            }

            return Http::response(['errorCode' => -1, 'msg' => 'unexpected URL'], 404);
        });

        $result = app(OmadaSpeedMonitor::class)->snapshot();

        $this->assertCount(1, $result);
        $this->assertSame('PAGINATION-SITE', $result[0]['site_code']);
        $this->assertSame(101, $result[0]['client_count']);
        $this->assertSame(101, $result[0]['wireless_count']);
        $this->assertCount(101, $result[0]['clients']);
    }

    /** @return array<string, mixed> */
    private function client(int $index): array
    {
        return [
            'mac' => sprintf('AA-BB-CC-%02X-%02X-%02X', ($index >> 16) & 255, ($index >> 8) & 255, $index & 255),
            'name' => 'Client '.$index,
            'ssid' => 'DICT',
            'apName' => 'AP-1',
            'wireless' => true,
            'rxRate' => 65_000,
            'txRate' => 65_000,
            'trafficDown' => 1_000,
            'trafficUp' => 500,
        ];
    }
}
