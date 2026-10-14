<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Omada External Portal Server adapter — receive side.
 *
 * Omada redirects the client's browser to the configured URL and appends
 * UNSIGNED query parameters (TP-Link doc 13080, controller 5.0.15–6.2.0):
 * clientMac, apMac, ssidName, t (microseconds since epoch), radioId, site,
 * redirectUrl.
 *
 * Because the parameters are unsigned, this adapter proves REDIRECT only: the
 * survey is shown and submissions are recorded with unverified attribution,
 * exactly like a site link. Verified attribution and the authorize-back call
 * to the controller arrive with controller API access (adapter, second half).
 * Nothing here grants network access.
 */
class OmadaAdapterController extends Controller
{
    public function __construct(
        private readonly ResolveSurvey $resolveSurvey,
    ) {}

    public function show(string $siteCode): View|RedirectResponse
    {
        $site = Site::query()->where('site_code', $siteCode)->first();

        if ($site === null || ! $this->validHandoff(request()->query())) {
            return redirect()->route('portal.unavailable');
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return redirect()->route('portal.unavailable');
        }

        // Same view and POST path as the public survey, so validation,
        // deduplication and (unverified) attribution cannot drift apart.
        return view('public.survey', [
            'site' => $site,
            'resolved' => $resolved,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    /**
     * Structural check only: the expected parameters are present and the
     * handoff is fresh. This detects misconfiguration and stale replays; it
     * does NOT authenticate the client (the parameters are unsigned).
     *
     * @param  array<string, mixed>  $query
     */
    private function validHandoff(array $query): bool
    {
        foreach (['clientMac', 'apMac', 't'] as $key) {
            if (! isset($query[$key]) || trim((string) $query[$key]) === '') {
                return false;
            }
        }

        if (! is_numeric($query['t'])) {
            return false;
        }

        // t is microseconds since epoch. A stale handoff opens nothing: the
        // user reconnects and Omada issues a fresh one.
        $issuedAt = ((float) $query['t']) / 1_000_000;
        $window = max(1, (int) config('portal.session_ttl_minutes', 15)) * 60;

        return abs(time() - $issuedAt) <= $window;
    }
}
