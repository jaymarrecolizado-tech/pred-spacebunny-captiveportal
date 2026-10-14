<?php

namespace Tests\Feature;

use App\Services\Portal\OmadaHotspotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The authorize-back call, against faked HTTP: the controller is unreachable
 * from here, so the contract with it is verified by shape, and degradation by
 * behavior.
 */
class OmadaHotspotClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(): array
    {
        return [
            'clientMac' => 'AA-BB-CC-DD-EE-FF',
            'apMac' => '0C-EF-15-CF-3B-1A',
            'ssidName' => 'NMS Test AP',
            'radioId' => '1',
            'site' => '68e88354bb5fdb13e8af0451',
        ];
    }

    private function configure(): void
    {
        config([
            'portal.omada.controller_url' => 'https://controller.example:8043',
            'portal.omada.controller_id' => 'abc123',
            'portal.omada.operator_username' => 'operator',
            'portal.omada.operator_password' => 'secret',
        ]);
    }

    public function test_an_unconfigured_client_refuses_quietly(): void
    {
        config([
            'portal.omada.controller_url' => null,
            'portal.omada.controller_id' => null,
            'portal.omada.operator_username' => null,
            'portal.omada.operator_password' => null,
        ]);

        Http::preventStrayRequests();

        $this->assertFalse(app(OmadaHotspotClient::class)->authorize($this->client()));
    }

    public function test_a_successful_login_and_authorize_returns_true(): void
    {
        $this->configure();

        Http::fake([
            '*/hotspot/login' => Http::response(['errorCode' => 0, 'result' => ['token' => 'csrf-token']]),
            '*/extPortal/auth' => Http::response(['errorCode' => 0]),
        ]);

        $this->assertTrue(app(OmadaHotspotClient::class)->authorize($this->client()));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/hotspot/login')
            && $request['name'] === 'operator');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/extPortal/auth')
            && $request->header('Csrf-Token') === ['csrf-token']
            && $request['clientMac'] === 'AA-BB-CC-DD-EE-FF'
            && $request['authType'] === 4);
    }

    public function test_a_rejected_login_returns_false_without_authorizing(): void
    {
        $this->configure();

        Http::fake([
            '*/hotspot/login' => Http::response(['errorCode' => -1, 'msg' => 'bad operator']),
        ]);

        $this->assertFalse(app(OmadaHotspotClient::class)->authorize($this->client()));

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/extPortal/auth'));
    }

    public function test_the_grant_duration_is_milliseconds_and_32_bit_safe(): void
    {
        $this->configure();

        Http::fake([
            '*/hotspot/login' => Http::response(['errorCode' => 0, 'result' => ['token' => 'csrf-token']]),
            '*/extPortal/auth' => Http::response(['errorCode' => 0]),
        ]);

        app(OmadaHotspotClient::class)->authorize($this->client());

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/extPortal/auth')) {
                return false;
            }

            $time = $request['time'];

            // 32-bit safe: microsecond-scale values overflow and the controller
            // silently produces a grant that is born expired.
            return $time > 0
                && $time <= 2147483647
                && $time === (int) config('portal.auth_duration_ms');
        });
    }

    public function test_a_rejected_authorize_returns_false(): void
    {
        $this->configure();

        Http::fake([
            '*/hotspot/login' => Http::response(['errorCode' => 0, 'result' => ['token' => 'csrf-token']]),
            '*/extPortal/auth' => Http::response(['errorCode' => -1]),
        ]);

        $this->assertFalse(app(OmadaHotspotClient::class)->authorize($this->client()));
    }

    public function test_a_connection_failure_returns_false(): void
    {
        $this->configure();

        Http::fake(fn () => throw new ConnectionException('unreachable'));

        $this->assertFalse(app(OmadaHotspotClient::class)->authorize($this->client()));
    }
}
