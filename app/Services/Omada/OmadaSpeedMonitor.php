<?php

namespace App\Services\Omada;

use App\Enums\RecordStatus;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A live snapshot of how fast each location is right now.
 *
 * The controller exposes no historical speed data - only the current rate of
 * every connected client. So "monitoring" here is a snapshot refreshed on
 * demand, not a trend. To get trends, poll this on a schedule and store the
 * results.
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

            if ($response === null || ! $response->successful()) {
                $result[] = [
                    'site_code' => $site->site_code,
                    'site_name' => $site->name,
                    'province' => $site->province,
                    'clients' => [],
                    'client_count' => 0,
                    'wireless_count' => 0,
                    'wired_count' => 0,
                    'rx_rate' => 0,
                    'tx_rate' => 0,
                    'error' => 'Could not read this location.',
                ];

                continue;
            }

            $body = $response->json();
            $rows = $body['result']['data'] ?? [];
            $total = (int) ($body['result']['totalRows'] ?? count($rows));

            $rx = 0;
            $tx = 0;
            $wireless = 0;
            $wired = 0;
            $clients = [];

            foreach ($rows as $row) {
                $isWireless = (bool) ($row['wireless'] ?? false);

                if ($isWireless) {
                    $wireless++;
                    $rx += (int) ($row['rxRate'] ?? 0);
                    $tx += (int) ($row['txRate'] ?? 0);
                } else {
                    $wired++;
                }

                $clients[] = [
                    'mac' => (string) ($row['mac'] ?? ''),
                    'name' => (string) ($row['name'] ?? $row['hostName'] ?? ''),
                    'ssid' => (string) ($row['ssid'] ?? ''),
                    'ap' => (string) ($row['apName'] ?? ''),
                    'wireless' => $isWireless,
                    'rxRate' => (int) ($row['rxRate'] ?? 0),
                    'txRate' => (int) ($row['txRate'] ?? 0),
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
                'rx_rate' => $rx,
                'tx_rate' => $tx,
                'error' => null,
            ];
        }

        // Sort by download speed, so the busy sites come first.
        usort($result, fn (array $a, array $b): int => $b['rx_rate'] <=> $a['rx_rate']);

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
