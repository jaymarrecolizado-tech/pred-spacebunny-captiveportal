<?php

namespace App\Services\Portal;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Authorize-back client for Omada External Portal (TP-Link doc 13080,
 * controller v5.0.15–6.2.0).
 *
 * Flow: hotspot-OPERATOR login → CSRF token + session cookie → extPortal/auth
 * carrying the client's connection parameters. It uses the hotspot operator
 * account created in the controller's hotspot manager — never the controller
 * admin account and never the OpenAPI client credentials.
 *
 * When unconfigured (or when the controller is unreachable) it returns false
 * and logs the reason instead of throwing: the user always gets an honest
 * answer, never a 500 and never a faked authorization.
 */
class OmadaHotspotClient
{
    public function configured(): bool
    {
        return filled(config('portal.omada.controller_url'))
            && filled(config('portal.omada.controller_id'))
            && filled(config('portal.omada.operator_username'))
            && filled(config('portal.omada.operator_password'));
    }

    /**
     * Ask the controller to authorize a client that completed the survey.
     * The $client values are echoed back exactly as Omada sent them.
     *
     * @param  array{clientMac: string, apMac: string, ssidName: string, radioId: string, site: string}  $client
     */
    public function authorize(array $client): bool
    {
        if (! $this->configured()) {
            Log::info('portal.omada.authorize_skipped', ['reason' => 'hotspot operator not configured']);

            return false;
        }

        try {
            // One client for both calls: the cookie jar carries the session
            // (TPEAP_SESSIONID or TPOMADA_SESSIONID, depending on controller
            // version) from login to authorize. A fresh jar per call silently
            // drops the session and the authorize is rejected.
            $http = $this->http();
            $token = $this->login($http);

            if ($token === null) {
                return false;
            }

            return $this->authorizeClient($http, $token, $client);
        } catch (\Throwable $e) {
            // The controller is unreachable or answered unexpectedly. That is
            // a normal failure mode (fail-open messaging), not a 500.
            Log::warning('portal.omada.authorize_failed', [
                'error' => $e->getMessage(),
                'clientMac' => $this->fingerprint($client['clientMac'] ?? ''),
            ]);

            return false;
        }
    }

    /**
     * Hotspot-operator login. Returns the CSRF token, keeping the session
     * cookie in the shared jar for the authorize call.
     */
    private function login(PendingRequest $http): ?string
    {
        $response = $http->post($this->url('api/v2/hotspot/login'), [
            'name' => config('portal.omada.operator_username'),
            'password' => config('portal.omada.operator_password'),
        ]);

        $token = $response->json('result.token');

        if ($response->json('errorCode') !== 0 || ! is_string($token) || $token === '') {
            Log::warning('portal.omada.login_rejected', [
                'errorCode' => $response->json('errorCode'),
            ]);

            return null;
        }

        return $token;
    }

    /**
     * @param  array{clientMac: string, apMac: string, ssidName: string, radioId: string, site: string}  $client
     */
    private function authorizeClient(PendingRequest $http, string $token, array $client): bool
    {
        $response = $http->withHeader('Csrf-Token', $token)->post(
            $this->url('api/v2/hotspot/extPortal/auth'),
            [
                'clientMac' => $client['clientMac'],
                'apMac' => $client['apMac'],
                'ssidName' => $client['ssidName'],
                'radioId' => $client['radioId'],
                'site' => $client['site'],
                // Authorization duration in milliseconds. See config/portal.php:
                // microseconds overflow the controller's 32-bit field and produce
                // a grant that is born expired (observed live: authorized client,
                // zero traffic).
                'time' => (int) config('portal.auth_duration_ms'),
                'authType' => 4,
            ]
        );

        $ok = $response->json('errorCode') === 0;

        // MACs are logged fingerprinted: enough to correlate, not to track.
        Log::info('portal.omada.authorize_attempt', [
            'ok' => $ok,
            'errorCode' => $response->json('errorCode'),
            'client' => $this->fingerprint($client['clientMac'] ?? ''),
        ]);

        return $ok;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('portal.omada.controller_url'), '/')
            .'/'.trim((string) config('portal.omada.controller_id'), '/')
            .'/'.ltrim($path, '/');
    }

    /**
     * One shared client: the cookie jar carries the session (TPEAP_SESSIONID
     * or TPOMADA_SESSIONID, depending on controller version) from login to
     * authorize. The controller uses a self-signed certificate, which the
     * official guide itself says to accept for this call.
     */
    private function http(): PendingRequest
    {
        return Http::withOptions([
            'cookies' => new CookieJar,
            'verify' => false,
            'timeout' => 10,
        ])->acceptJson();
    }

    private function fingerprint(string $mac): string
    {
        return $mac === '' ? 'unknown' : substr(hash('sha256', strtolower($mac)), 0, 12);
    }
}
