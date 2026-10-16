<?php

namespace App\Services\Omada;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Omada Open API.
 *
 * Deliberately read-only. This is the credential that can read all 193
 * locations on the government network, so nothing here can change gateway
 * configuration - a compromised secret can read, not reconfigure.
 *
 * Omada facts learned live against the real controller, not from docs:
 *  - the base URL is the "Interface Access Address" shown in the Open API
 *    screen, which already ends in /openapi, and the controller serves a
 *    self-signed certificate (the same one the hotspot client already accepts);
 *  - page endpoints need `page` and `pageSize`, and 400 without them;
 *  - the row count is `totalRows`, not `total`.
 */
class OmadaOpenApiClient
{
    private ?string $token = null;

    private ?Carbon $tokenExpiry = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return $this->config['client_id'] !== null
            && $this->config['client_secret'] !== null
            && $this->config['omadac_id'] !== null
            && $this->config['base_url'] !== null;
    }

    /**
     * @throws \RuntimeException when the controller refuses the credentials.
     */
    public function sites(): array
    {
        $all = [];
        $page = 1;

        do {
            $result = $this->get('/v1/'.$this->config['omadac_id'].'/sites?page='.$page.'&pageSize=200');
            $batch = $result['data'] ?? [];
            $all = array_merge($all, $batch);

            // Guard against a controller that reports a total it never reaches.
            if ($batch === [] || $page > 100) {
                break;
            }

            $page++;
        } while (count($all) < (int) ($result['totalRows'] ?? 0));

        return $all;
    }

    /**
     * Client count for one site. Only the number is ever read; the payload also
     * carries MAC addresses, IPs, hostnames and traffic, and none of it is kept.
     */
    public function clientCount(string $siteId): ?int
    {
        $result = $this->get('/v1/'.$this->config['omadac_id'].'/sites/'.$siteId.'/clients?page=1&pageSize=1');

        return isset($result['totalRows']) ? (int) $result['totalRows'] : null;
    }

    /**
     * Perform a GET and unwrap the result envelope.
     *
     * Omada answers as {errorCode, msg, result: {...}}, so every caller works
     * with result directly. Reading the top level instead silently yields an
     * empty list rather than an error, which is exactly the mistake this
     * wrapper exists to make impossible.
     *
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        // withHeaders(), not the second argument of get(): Laravel's get($url,
        // $query) takes QUERY PARAMETERS, so passing the Authorization header
        // there silently sends it in the URL and the controller answers 401.
        $response = $this->request()
            ->withHeaders(['Authorization' => 'AccessToken='.$this->token()])
            ->get($this->base().$path);

        $body = $this->decode($response);

        if (($body['errorCode'] ?? -1) !== 0) {
            throw new \RuntimeException(sprintf(
                'Omada rejected %s (HTTP %d): %s %s',
                $path,
                $response->status(),
                $body['errorCode'] ?? '?',
                $body['msg'] ?? 'no message',
            ));
        }

        return (array) ($body['result'] ?? []);
    }

    /**
     * Client-credentials token flow. Cached until shortly before it expires,
     * because every call is otherwise a second round trip and Omada rate-limits.
     *
     * @return array<string, mixed>
     */
    private function token(): string
    {
        if ($this->token !== null && $this->tokenExpiry !== null && $this->tokenExpiry->isFuture()) {
            return $this->token;
        }

        $response = $this->request()->post(
            $this->base().'/authorize/token?grant_type=client_credentials',
            [
                'omadacId' => $this->config['omadac_id'],
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ],
        );

        $body = $this->decode($response);

        if (($body['errorCode'] ?? -1) !== 0 || ! isset($body['result']['accessToken'])) {
            throw new \RuntimeException(sprintf(
                'Omada refused the credentials: %s %s',
                $body['errorCode'] ?? '?',
                $body['msg'] ?? 'no message',
            ));
        }

        $this->token = (string) $body['result']['accessToken'];
        // Five minutes early, so a token cannot expire mid-request.
        $this->tokenExpiry = now()->addSeconds(max(60, (int) ($body['result']['expiresIn'] ?? 7200) - 300));

        return $this->token;
    }

    private function base(): string
    {
        return rtrim((string) $this->config['base_url'], '/');
    }

    private function request(): PendingRequest
    {
        return Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            // The controller presents its factory self-signed certificate. The
            // working hotspot client already accepts it; this keeps the two
            // integrations consistent.
            ->withOptions(['verify' => (bool) ($this->config['verify_tls'] ?? false)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException(sprintf(
                'Omada returned a non-JSON response (HTTP %d). Is the base URL correct?',
                $response->status(),
            ));
        }

        return $body;
    }
}
