<?php

namespace App\Services\Omada;

use App\Enums\RecordStatus;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;

/**
 * A live snapshot of how fast each location is right now.
 *
 * The controller exposes no historical speed data - only the current rate of
 * every connected client. So "monitoring" here is a snapshot refreshed on
 * demand, not a trend. To get trends, poll this on a schedule and store the
 * results (see the note on the speed monitor page).
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
        $client = app(OmadaOpenApiClient::class);

        if (! $client->isConfigured()) {
            return [];
        }

        // Only locations that have actually been reached by the controller.
        // The other 150 are still being set up and would only slow the page down.
        $sites = Site::query()
            ->where('status', RecordStatus::Active)
            ->whereNotNull('portal_last_seen_at')
            ->orderBy('site_code')
            ->get();

        $result = [];

        foreach ($sites as $site) {
            $siteId = $site->omada_site_id;

            if ($siteId === null) {
                continue;
            }

            $result[] = $this->siteSnapshot($site, $client, $siteId);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function siteSnapshot(Site $site, OmadaOpenApiClient $client, string $siteId): array
    {
        $clients = [];
        $totalRx = 0; // download, Kbps
        $totalTx = 0; // upload, Kbps
        $wireless = 0;
        $wired = 0;
        $totalClients = 0;

        try {
            $page = 1;
            $seen = 0;
            $total = null;

            do {
                $result = $client->getClientsPage($siteId, $page, 100);

                if ($total === null) {
                    $total = (int) ($result['totalRows'] ?? 0);
                }

                foreach (($result['data'] ?? []) as $row) {
                    $seen++;
                    $isWireless = (bool) ($row['wireless'] ?? false);

                    if ($isWireless) {
                        $wireless++;
                        $totalRx += (int) ($row['rxRate'] ?? 0);
                        $totalTx += (int) ($row['txRate'] ?? 0);
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

                $page++;
            } while ($total !== null && $seen < $total && $seen < 1000);

            $totalClients = $total ?? 0;
        } catch (\Throwable $e) {
            // A site that refuses the query still shows up, with zero speed and
            // the error noted. That is more honest than silently dropping it.
            return [
                'site_code' => $site->site_code,
                'site_name' => $site->name,
                'province' => $site->province,
                'clients' => [],
                'client_count' => 0,
                'wireless_count' => 0,
                'wired_count' => 0,
                'rx_rate' => 0,
                'tx_rate' => 0,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'site_code' => $site->site_code,
            'site_name' => $site->name,
            'province' => $site->province,
            'clients' => $clients,
            'client_count' => $totalClients,
            'wireless_count' => $wireless,
            'wired_count' => $wired,
            'rx_rate' => $totalRx,
            'tx_rate' => $totalTx,
            'error' => null,
        ];
    }

    /**
     * Clear the cached snapshot so the next page load fetches fresh data.
     */
    public function refresh(): void
    {
        Cache::forget('omada.speed.snapshot');
    }
}
