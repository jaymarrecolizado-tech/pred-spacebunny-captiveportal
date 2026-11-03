<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Omada\OmadaSpeedMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Downloads the current speed snapshot as a CSV.
 */
class SpeedMonitorController extends Controller
{
    public function download(Request $request, OmadaSpeedMonitor $monitor): Response
    {
        $snapshot = $monitor->snapshot();
        $lines = [];

        $lines[] = ['Site', 'Name', 'Province', 'Clients', 'Wireless', 'Wired', 'Download (Mbps)', 'Upload (Mbps)'];

        foreach ($snapshot as $site) {
            $lines[] = [
                $site['site_code'],
                $site['site_name'],
                $site['province'] ?? '',
                $site['client_count'],
                $site['wireless_count'],
                $site['wired_count'],
                round($site['rx_rate'] / 1000, 2),
                round($site['tx_rate'] / 1000, 2),
            ];
        }

        // Per-client detail.
        $lines[] = [];
        $lines[] = ['Site', 'Device', 'MAC', 'SSID', 'Access point', 'Download (Mbps)', 'Upload (Mbps)', 'Signal (dBm)', 'Signal rank'];

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
                    round($client['rxRate'] / 1000, 2),
                    round($client['txRate'] / 1000, 2),
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
            'Content-Disposition' => 'attachment; filename="speed-snapshot-'.now()->format('Y-m-d-His').'.csv"',
        ]);
    }
}
