<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SpeedClientSample;
use App\Models\SpeedSiteSample;
use App\Services\Omada\OmadaSpeedMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores one sample per active location.
 *
 * Actual traffic is calculated from the change in cumulative client traffic
 * counters between consecutive samples. Clients that were not present for both
 * samples are ignored, because their counters cannot be matched, and a counter
 * that goes backwards is ignored too. This is deliberately conservative:
 * it may under-count new devices, but it should not invent traffic.
 */
class SampleSpeedRates extends Command
{
    protected $signature = 'omada:speed-sample';

    protected $description = 'Store current client traffic counters and calculate actual traffic since the previous sample';

    public function handle(OmadaSpeedMonitor $monitor): int
    {
        $monitor->refresh();
        $snapshot = $monitor->snapshot();

        if ($snapshot === []) {
            $this->error('The controller returned no location data. Check the Omada settings and token.');

            return self::FAILURE;
        }

        $sites = Site::whereIn('site_code', array_column($snapshot, 'site_code'))
            ->get()
            ->keyBy('site_code');

        $capturedAt = now();
        $sampled = 0;
        $errors = 0;
        $totalDeltaDown = 0;
        $totalDeltaUp = 0;

        DB::transaction(function () use ($snapshot, $sites, $capturedAt, &$sampled, &$errors, &$totalDeltaDown, &$totalDeltaUp): void {
            foreach ($snapshot as $row) {
                $site = $sites->get($row['site_code'] ?? '');

                if ($site === null) {
                    continue;
                }

                $last = SpeedSiteSample::where('site_id', $site->id)
                    ->latest('captured_at')
                    ->first();

                $intervalSeconds = $last ? max(1, (int) abs($capturedAt->diffInSeconds($last->captured_at))) : null;
                $previousClients = $last
                    ? SpeedClientSample::where('site_id', $site->id)
                        ->where('captured_at', $last->captured_at)
                        ->get(['mac_hash', 'traffic_down_bytes', 'traffic_up_bytes'])
                        ->keyBy('mac_hash')
                    : collect();

                $current = $this->clientCounters($row['clients'] ?? []);
                $counter = $this->compareCounters($previousClients, $current);
                $downMbps = $intervalSeconds === null || $counter['delta_down'] === null
                    ? null
                    : ($counter['delta_down'] * 8) / $intervalSeconds / 1000000;
                $upMbps = $intervalSeconds === null || $counter['delta_up'] === null
                    ? null
                    : ($counter['delta_up'] * 8) / $intervalSeconds / 1000000;

                SpeedSiteSample::create([
                    'site_id' => $site->id,
                    'captured_at' => $capturedAt,
                    'client_count' => (int) ($row['client_count'] ?? 0),
                    'wireless_count' => (int) ($row['wireless_count'] ?? 0),
                    'wired_count' => (int) ($row['wired_count'] ?? 0),
                    'ap_to_client_max_kbps' => (int) ($row['ap_to_client_max_kbps'] ?? 0),
                    'client_to_ap_max_kbps' => (int) ($row['client_to_ap_max_kbps'] ?? 0),
                    'low_rate_wireless_count' => (int) ($row['low_rate_wireless_count'] ?? 0),
                    'delta_down_bytes' => $counter['delta_down'],
                    'delta_up_bytes' => $counter['delta_up'],
                    'sample_interval_seconds' => $intervalSeconds,
                    'matched_clients' => $counter['matched'],
                    'unmatched_clients' => $counter['unmatched'],
                    'down_mbps' => $downMbps,
                    'up_mbps' => $upMbps,
                    'error' => $row['error'] ?? null,
                ]);

                foreach ($current as $hash => $counters) {
                    SpeedClientSample::create([
                        'site_id' => $site->id,
                        'captured_at' => $capturedAt,
                        'mac_hash' => $hash,
                        'traffic_down_bytes' => $counters['down'],
                        'traffic_up_bytes' => $counters['up'],
                    ]);
                }

                $sampled++;
                $totalDeltaDown += (int) ($counter['delta_down'] ?? 0);
                $totalDeltaUp += (int) ($counter['delta_up'] ?? 0);

                if (isset($row['error']) && $row['error']) {
                    $errors++;
                }
            }

            // Client counter snapshots are only needed to calculate the next
            // sample. Keeping two days gives scheduling some room while a week
            // of high-frequency rows would serve no purpose.
            SpeedClientSample::where('captured_at', '<', now()->subDays(2))->delete();
        });

        $this->info("Stored {$sampled} site samples at {$capturedAt->toDateTimeString()}.");

        if ($errors > 0) {
            $this->warn("{$errors} location(s) could not be read and were stored with their error.");
        }

        $this->line(sprintf('Matched client traffic: %.2f MB down, %.2f MB up.', $totalDeltaDown / 1048576, $totalDeltaUp / 1048576));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $clients
     * @return array<string, array{down: int, up: int}>
     */
    private function clientCounters(array $clients): array
    {
        $current = [];
        $key = config('app.key', 'speed-samples');

        foreach ($clients as $client) {
            $mac = Str::upper(trim((string) ($client['mac'] ?? '')));

            if ($mac === '') {
                continue;
            }

            $current[hash_hmac('sha256', $mac, (string) $key)] = [
                'down' => (int) ($client['trafficDown'] ?? 0),
                'up' => (int) ($client['trafficUp'] ?? 0),
            ];
        }

        return $current;
    }

    /**
     * @param  Collection<string, SpeedClientSample>  $previous
     * @param  array<string, array{down: int, up: int}>  $current
     * @return array{delta_down: int|null, delta_up: int|null, matched: int, unmatched: int}
     */
    private function compareCounters(Collection $previous, array $current): array
    {
        if ($previous->isEmpty()) {
            return [
                'delta_down' => null,
                'delta_up' => null,
                'matched' => 0,
                'unmatched' => count($current),
            ];
        }

        $deltaDown = 0;
        $deltaUp = 0;
        $matched = 0;
        $unmatched = 0;
        $sawValidDelta = false;

        foreach ($current as $hash => $counters) {
            $before = $previous->get($hash);

            if ($before === null) {
                $unmatched++;

                continue;
            }

            $down = $counters['down'] - (int) $before->traffic_down_bytes;
            $up = $counters['up'] - (int) $before->traffic_up_bytes;

            if ($down < 0 || $up < 0) {
                // Counter went backwards (likely a reconnect/reset). We cannot
                // trust either delta for this client.
                $unmatched++;

                continue;
            }

            $deltaDown += $down;
            $deltaUp += $up;
            $matched++;
            $sawValidDelta = true;
        }

        return [
            'delta_down' => $sawValidDelta ? $deltaDown : null,
            'delta_up' => $sawValidDelta ? $deltaUp : null,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }
}
