<?php

namespace App\Http\Controllers\Simulator;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\PortalSession;
use App\Models\Site;
use App\Services\Portal\PortalToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A stand-in gateway, used to exercise the captive portal contract end to end
 * without hardware.
 *
 * It behaves the way a real gateway must: it calls the server-to-server
 * endpoints with its own credentials, sends the user's browser to the survey
 * URL it was given, and only then asks whether the survey was completed before
 * it decides anything.
 *
 * Development only (config('portal.simulator_enabled')).
 */
class GatewayController extends Controller
{
    public function index(): Response
    {
        $this->guard();

        return response()->view('simulator.index', [
            'integrations' => Integration::with('site')->orderBy('id')->get(),
            'sites' => Site::query()->visibleTo(Auth::user())->orderBy('site_code')->get(),
            'sessions' => PortalSession::with('site')->latest('issued_at')->limit(10)->get(),
        ]);
    }

    /**
     * Step 1: open a session, then redirect the user to the survey.
     */
    public function connect(Request $request): RedirectResponse
    {
        $this->guard();

        $data = $request->validate([
            'integration_id' => ['required', 'integer'],
            'site_code' => ['required', 'string'],
        ]);

        $integration = Integration::findOrFail($data['integration_id']);
        $site = Site::where('site_code', $data['site_code'])->firstOrFail();

        $response = $this->callContract('init', $integration, [
            'site_code' => $site->site_code,
            // Stands in for the gateway's own session/MAC reference.
            'gateway_session_ref' => 'sim-'.Str::random(10),
        ]);

        if ($response->getStatusCode() >= 400) {
            return back()->withInput()->withErrors([
                'gateway' => 'init refused: '.$this->messageOf($response->getContent(), $response->getStatusCode()),
            ]);
        }

        // A real gateway redirects the client here. So does this one.
        return redirect()->away(json_decode($response->getContent(), true)['data']['survey_url']);
    }

    /**
     * Step 3: ask the platform whether the survey was completed, then apply
     * the gateway's own access policy to that answer.
     */
    public function status(Request $request): Response
    {
        $this->guard();

        $data = $request->validate([
            'integration_id' => ['required', 'integer'],
            'session_reference' => ['required', 'uuid'],
        ]);

        $integration = Integration::findOrFail($data['integration_id']);
        $response = $this->callContract('status', $integration, [
            'session_reference' => $data['session_reference'],
        ]);

        $outcome = $response->getStatusCode() === 200
            ? json_decode($response->getContent(), true)['data']
            : null;

        return response()->view('simulator.status', [
            'integration' => $integration,
            'outcome' => $outcome,
            'error' => $outcome === null
                ? $this->messageOf($response->getContent(), $response->getStatusCode())
                : null,
        ]);
    }

    /**
     * The negative test that matters: a tampered session reference must not
     * open a survey (FR-CAP-004, Spec 9.5).
     */
    public function tamper(Request $request): Response
    {
        $this->guard();

        $data = $request->validate(['session_reference' => ['required', 'uuid']]);
        $session = PortalSession::with('integration')->findOrFail($data['session_reference']);

        $real = route('portal.session', [
            'session' => $session->id,
            'token' => PortalToken::mint($session),
        ]);

        return response()->view('simulator.tamper', [
            'session' => $session,
            'tamperedUrl' => str_replace($session->id, (string) Str::uuid(), $real),
        ]);
    }

    /**
     * Call our own contract endpoint through the real HTTP kernel, so the
     * simulator exercises routing, middleware and authentication exactly as an
     * external gateway would.
     */
    private function callContract(string $endpoint, Integration $integration, array $payload): Response
    {
        return app()->handle(Request::create(
            uri: '/api/v1/integrations/portal/'.$endpoint,
            method: 'POST',
            parameters: [],
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_INTEGRATION_ID' => $integration->public_identifier,
                'HTTP_X_INTEGRATION_SECRET' => $integration->secret,
            ]),
            content: json_encode($payload),
        ));
    }

    private function guard(): void
    {
        abort_unless(config('portal.simulator_enabled'), 404);
        // It is a gateway console, so it sits behind the same authentication as
        // any administrative surface.
        abort_unless(Auth::check() && Auth::user()->role->managesRegistry(), 403);
    }

    private function messageOf(?string $content, int $status): string
    {
        $decoded = json_decode((string) $content, true);

        return $decoded['error']['message'] ?? 'Unknown error (HTTP '.$status.')';
    }
}
