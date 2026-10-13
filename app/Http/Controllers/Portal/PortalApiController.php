<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Portal\PortalContractException;
use App\Services\Portal\PortalSessionService;
use App\Services\Portal\PortalToken;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

/**
 * Server-to-server half of the captive portal contract (Spec 11.2).
 *
 * These endpoints are called by the gateway. None of them grants network
 * access on their own: `status` only reports an outcome the browser produced.
 */
class PortalApiController extends Controller
{
    public function __construct(
        private readonly PortalSessionService $sessions,
        private readonly ResolveSurvey $resolveSurvey,
    ) {}

    /**
     * POST /api/v1/integrations/portal/init
     *
     * Validate a gateway handoff and create a short-lived survey session.
     */
    public function init(Request $request): JsonResponse
    {
        $integration = $request->attributes->get('integration');

        $data = $request->validate([
            'site_code' => ['nullable', 'string', 'max:64'],
            'gateway_session_ref' => ['nullable', 'string', 'max:128'],
            'redirect_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $site = null;

        if (($data['site_code'] ?? null) !== null) {
            $site = Site::query()->where('site_code', $data['site_code'])->first();

            if ($site === null) {
                return $this->fail('unknown_site', 'That site code is not registered.', 404);
            }
        } elseif ($integration->site_id !== null) {
            $site = $integration->site;
        }

        if ($site === null) {
            return $this->fail('site_required', 'This integration requires a site_code.', 422);
        }

        // No survey means no session to hand off to; the gateway is told the
        // public survey is unavailable rather than being sent nowhere.
        if ($this->resolveSurvey->forSite($site) === null) {
            return $this->fail('survey_unavailable', 'Feedback is not being collected at this site.', 409);
        }

        $session = $this->sessions->open($integration, $site, [
            'gateway_session_ref' => $data['gateway_session_ref'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'session_reference' => $session->id,
                'expires_at' => $session->expires_at->toIso8601String(),
                'survey_url' => route('portal.session', [
                    'session' => $session->id,
                    'token' => PortalToken::mint($session),
                ]),
                'site_code' => $site->site_code,
                'policy' => $session->integration->is_test_mode ? 'test' : 'optional',
            ],
        ], 201);
    }

    /**
     * POST /api/v1/integrations/portal/status
     *
     * The gateway's own decision input. It answers from stored state only.
     */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_reference' => ['required', 'uuid'],
        ]);

        $integration = $request->attributes->get('integration');
        $session = $integration->sessions()->find($data['session_reference']);

        // A session belonging to another integration is reported as unknown
        // rather than forbidden, so the endpoint leaks no cross-integration data.
        if ($session === null) {
            return $this->fail('unknown_session', 'Unknown session reference.', 404);
        }

        return response()->json(['data' => $this->sessions->outcome($session)]);
    }

    /**
     * POST /api/v1/integrations/portal/complete
     *
     * For gateways that record completion themselves instead of polling.
     * Idempotent by design.
     */
    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_reference' => ['required', 'uuid'],
        ]);

        $integration = $request->attributes->get('integration');
        $session = $integration->sessions()->find($data['session_reference']);

        if ($session === null) {
            return $this->fail('unknown_session', 'Unknown session reference.', 404);
        }

        try {
            $session = $this->sessions->complete($session);
        } catch (PortalContractException $e) {
            return $this->fail($e->code, $e->getMessage(), $e->status);
        }

        return response()->json(['data' => $this->sessions->outcome($session)]);
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'correlation_id' => Context::get('correlation_id'),
            ],
        ], $status);
    }
}
