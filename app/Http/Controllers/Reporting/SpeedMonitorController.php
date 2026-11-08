<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Omada\OmadaSpeedMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Downloads the current wireless link-rate snapshot as a CSV.
 */
class SpeedMonitorController extends Controller
{
    public function download(Request $request, OmadaSpeedMonitor $monitor): Response
    {
        $snapshot = $monitor->snapshot();
        $lines = [];

        $lines[] = ['Site', 'Name', 'Province', 'Clients', 'Wireless', 'Wired', 'Peak AP to Device link (Mbps)', 'Peak Device to AP link (Mbps)', 'Basic/low-rate devices'];

        foreach ($snapshot as $site) {
            $lines[] = [
                $site['site_code'],
                $site['site_name'],
                $site['province'] ?? '',
                $site['client_count'],
                $site['wireless_count'],
                $site['wired_count'],
                round($site['ap_to_client_max_kbps'] / 1000, 2),
                round($site['client_to_ap_max_kbps'] / 1000, 2),
                $site['low_rate_wireless_count'] ?? 0,
            ];
        }

        // Per-client detail.
        $lines[] = [];
        $lines[] = ['Site', 'Device', 'MAC', 'SSID', 'Access point', 'AP to Device link (Mbps)', 'Device to AP link (Mbps)', 'Signal (dBm)', 'Signal rank'];

        foreach ($snapshot as $site) {
            foreach ($site['clients'] as $client) {
                if (! $client['wireless']) {
                    continue;
                }

                $lines[] = [
                    $site['site_code'],
                    $client['name'] ?: $client['mac'],
                    $client['mac'],
                    $client['ssid'],
                    $client['ap'],
                    round($client['ap_to_client_kbps'] / 1000, 2),
                    round($client['client_to_ap_kbps'] / 1000, 2),
                    $client['rssi'] ?? '',
                    $client['signal'],
                ];
            }
        }

        $handle = fopen('php://temp', 'r+');

        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="wireless-link-snapshot-'.now()->format('Y-m-d-His').'.csv"',
        ]);
    }
}
