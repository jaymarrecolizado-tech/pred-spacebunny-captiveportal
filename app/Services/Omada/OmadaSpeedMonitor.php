<?php

namespace App\Services\Omada;

use App\Enums\RecordStatus;
use App\Models\Site;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A live snapshot of each location's current wireless link rates.
 *
 * These numbers are NOT measured internet throughput and are not a speed test.
 * They are the negotiated radio rates reported by every connected client:
 *
 * - AP -> Device is Omada's txRate (the access point transmitting to the device)
 * - Device -> AP is Omada's rxRate (the access point receiving from the device)
 *
 * A snapshot is not a trend. To measure actual traffic, poll the cumulative
 * trafficDown/trafficUp counters on a schedule and calculate the change over
 * time; a single snapshot cannot show throughput.
 *
 * All live sites are fetched concurrently, so 193 locations take about as
 * long as one.
 */
class OmadaSpeedMonitor
{
    private const CACHE_SECONDS = 60;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(): array
    {
        return Cache::remember('omada.speed.snapshot', self::CACHE_SECONDS, function (): array {
            return $this->fetch();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(): array
    {
        $config = config('omada');

        if (blank($config['client_id'] ?? null) || blank($config['client_secret'] ?? null) || blank($config['omadac_id'] ?? null) || blank($config['base_url'] ?? null)) {
            return [];
        }

        // All active sites, not just the ones that have been reached by the
        // controller. A location with 44 clients but no portal handoff yet is
        // exactly the one an operator wants to see.
        $sites = Site::query()
            ->where('status', RecordStatus::Active)
            ->orderBy('site_code')
            ->get();

        if ($sites->isEmpty()) {
            return [];
        }

        // Token first (one call), then fan out.
        $http = Http::withOptions(['verify' => (bool) ($config['verify_tls'] ?? false), 'timeout' => 30])
            ->withHeaders(['Content-Type' => 'application/json']);

        $login = $http->post(rtrim((string) $config['base_url'], '/').'/authorize/token?grant_type=client_credentials', [
            'omadacId' => $config['omadac_id'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        $token = $login->json('result.accessToken');

        if (! is_string($token) || $token === '') {
            return [];
        }

        $base = rtrim((string) $config['base_url'], '/');
        $omadacId = $config['omadac_id'];

        // One concurrent request per site. pageSize=100 captures every client
        // for all but the largest locations in a single round trip.
        $responses = Http::pool(function ($pool) use ($sites, $base, $omadacId, $token) {
            $calls = [];

            foreach ($sites as $site) {
                if ($site->omada_site_id === null) {
                    continue;
                }

                $calls[] = $pool->as((string) $site->id)
                    ->withOptions(['verify' => (bool) (config('omada.verify_tls') ?? false), 'timeout' => 30])
                    ->withHeaders(['Content-Type' => 'application/json', 'Authorization' => 'AccessToken='.$token])
                    ->get($base."/v1/{$omadacId}/sites/{$site->omada_site_id}/clients?page=1&pageSize=100");
            }

            return $calls;
        });

        $result = [];

        foreach ($sites as $site) {
            if ($site->omada_site_id === null) {
                continue;
            }

            $response = $responses[(string) $site->id] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $result[] = [
                    'site_code' => $site->site_code,
                    'site_name' => $site->name,
                    'province' => $site->province,
                    'clients' => [],
                    'client_count' => 0,
                    'wireless_count' => 0,
                    'wired_count' => 0,
                    'ap_to_client_max_kbps' => 0,
                    'client_to_ap_max_kbps' => 0,
                    'low_rate_wireless_count' => 0,
                    'error' => 'Could not read this location.',
                ];

                continue;
            }

            $body = $response->json();
            $rows = $body['result']['data'] ?? [];
            $total = (int) ($body['result']['totalRows'] ?? count($rows));

            $clientToApMax = 0;
            $apToClientMax = 0;
            $lowRate = 0;
            $wireless = 0;
            $wired = 0;
            $clients = [];

            foreach ($rows as $row) {
                $isWireless = (bool) ($row['wireless'] ?? false);

                if ($isWireless) {
                    $wireless++;
                    $clientToApRate = (int) ($row['rxRate'] ?? 0);
                    $apToClientRate = (int) ($row['txRate'] ?? 0);
                    $clientToApMax = max($clientToApMax, $clientToApRate);
                    $apToClientMax = max($apToClientMax, $apToClientRate);

                    // Wi-Fi basic rates top out around 12 Mbps. Anything at or below that
                    // is a client that is probably too far away or too old.
                    if ($clientToApRate <= 12000 || $apToClientRate <= 12000) {
                        $lowRate++;
                    }
                } else {
                    $wired++;
                }

                $clients[] = [
                    'mac' => (string) ($row['mac'] ?? ''),
                    'name' => (string) ($row['name'] ?? $row['hostName'] ?? ''),
                    'ssid' => (string) ($row['ssid'] ?? ''),
                    'ap' => (string) ($row['apName'] ?? ''),
                    'wireless' => $isWireless,
                    'client_to_ap_kbps' => (int) ($row['rxRate'] ?? 0),
                    'ap_to_client_kbps' => (int) ($row['txRate'] ?? 0),
                    'trafficDown' => (int) ($row['trafficDown'] ?? 0),
                    'trafficUp' => (int) ($row['trafficUp'] ?? 0),
                    'rssi' => isset($row['rssi']) ? (int) $row['rssi'] : null,
                    'signal' => (int) ($row['signalRank'] ?? 0),
                    'guest' => (bool) ($row['guest'] ?? false),
                    'active' => (bool) ($row['active'] ?? false),
                ];
            }

            $result[] = [
                'site_code' => $site->site_code,
                'site_name' => $site->name,
                'province' => $site->province,
                'clients' => $clients,
                'client_count' => $total,
                'wireless_count' => $wireless,
                'wired_count' => $wired,
                'ap_to_client_max_kbps' => $apToClientMax,
                'client_to_ap_max_kbps' => $clientToApMax,
                'low_rate_wireless_count' => $lowRate,
                'error' => null,
            ];
        }

        // Put the strongest AP-to-client link rates first, not "traffic".
        usort($result, fn (array $a, array $b): int => $b['ap_to_client_max_kbps'] <=> $a['ap_to_client_max_kbps']);

        return $result;
    }

    /**
     * Clear the cached snapshot so the next page load fetches fresh data.
     */
    public function refresh(): void
    {
        Cache::forget('omada.speed.snapshot');
    }
}
