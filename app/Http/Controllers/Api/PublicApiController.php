<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttributionMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurveyResponseRequest;
use App\Models\Site;
use App\Services\Responses\SubmitResponse;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * Public JSON contract, Spec section 11.1.
 */
class PublicApiController extends Controller
{
    public function __construct(
        private readonly ResolveSurvey $resolveSurvey,
        private readonly SubmitResponse $submitResponse,
    ) {}

    /**
     * GET /api/v1/public/sites/{siteCode}/survey
     *
     * Site-link mode only, and the payload never claims verified Wi-Fi session
     * status (Spec 11.1).
     */
    public function survey(string $siteCode): JsonResponse
    {
        $site = Site::query()->where('site_code', $siteCode)->first();

        if ($site === null) {
            return $this->error('site_unavailable', 'This feedback page is not available.', 404);
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return $this->error('survey_unavailable', 'Feedback is not being collected at this site right now.', 404);
        }

        return response()->json([
            'data' => [
                'site' => [
                    'site_code' => $site->site_code,
                    'name' => $site->name,
                    'province' => $site->province,
                    'municipality' => $site->municipality,
                ],
                'attribution' => [
                    'method' => AttributionMethod::QrCode->value,
                    'verified_wifi_session' => false,
                ],
                'policy' => $resolved->policy->value,
                'survey_version' => [
                    'version' => $resolved->version->version_number,
                    'privacy_notice_version' => $resolved->version->privacy_notice_version,
                    'questions' => $resolved->version->questions(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/public/responses
     */
    public function store(StoreSurveyResponseRequest $request): JsonResponse
    {
        $site = Site::query()->where('site_code', $request->validated('site_code'))->first();

        if ($site === null) {
            return $this->error('site_unavailable', 'This feedback page is not available.', 404);
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return $this->error('survey_unavailable', 'Feedback is not being collected at this site right now.', 409);
        }

        $response = $this->submitResponse->handle(
            site: $site,
            resolved: $resolved,
            answers: $request->validated('answers'),
            attribution: $site->publicAttributionMethod(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return response()->json([
            'data' => [
                'id' => $response->id,
                'reference' => substr($response->id, 0, 8),
                'site_code' => $site->site_code,
                'attribution_method' => $response->attribution_method->value,
                'submitted_at' => $response->submitted_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/public/health — availability only, no topology or secrets.
     */
    public function health(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'unavailable';
        }

        $healthy = $database === 'ok';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => ['database' => $database],
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function error(string $code, string $message, int $status): JsonResponse
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
