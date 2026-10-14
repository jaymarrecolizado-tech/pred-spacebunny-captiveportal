<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AttributionMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurveyResponseRequest;
use App\Models\Site;
use App\Services\Portal\OmadaHotspotClient;
use App\Services\Responses\SubmitResponse;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Omada External Portal Server adapter — receive side.
 *
 * Omada redirects the client's browser to the configured URL and appends
 * UNSIGNED query parameters (TP-Link doc 13080, controller 5.0.15–6.2.0):
 * clientMac, apMac, ssidName, t, radioId, site, redirectUrl.
 *
 * Observed live on DICT Centralized Omada: t arrives in SECONDS (not the
 * documented microseconds) and site is an opaque controller-side ID, not the
 * site name. The adapter therefore binds the site via the URL path, never via
 * the site parameter, and accepts all three time units.
 *
 * After the survey, the adapter attempts the documented authorize-back call
 * (hotspot-operator login → extPortal/auth). Until working operator
 * credentials exist, that attempt degrades gracefully and the user is told
 * the truth instead of being promised internet.
 */
class OmadaAdapterController extends Controller
{
    public function __construct(
        private readonly ResolveSurvey $resolveSurvey,
        private readonly SubmitResponse $submitResponse,
        private readonly OmadaHotspotClient $hotspot,
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

        // Same view as the public survey, but posting back here so the
        // Omada handoff parameters survive to the authorize step.
        return view('public.survey', [
            'site' => $site,
            'resolved' => $resolved,
            'idempotencyKey' => (string) Str::uuid(),
            'postUrl' => route('portal.omada.store', ['siteCode' => $site->site_code])
                .'?'.http_build_query(request()->query()),
        ]);
    }

    public function store(string $siteCode, StoreSurveyResponseRequest $request): RedirectResponse
    {
        $site = Site::query()->where('site_code', $siteCode)->first();
        $handoff = $request->query();

        // The handoff is re-validated on POST: the form URL alone must never
        // be enough to submit through this path.
        if ($site === null || ! $this->validHandoff($handoff)) {
            return redirect()->route('portal.unavailable');
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return redirect()->route('portal.unavailable');
        }

        $clean = $this->submitResponse->validateAnswers($resolved->version, $request->validated('answers'));

        $response = $this->submitResponse->handle(
            site: $site,
            resolved: $resolved,
            answers: $clean,
            // Unsigned redirect: unverified, exactly like a site link.
            attribution: AttributionMethod::SiteLink,
            idempotencyKey: $request->validated('idempotency_key'),
            // Hashed server-side; lets a later authorize verification correlate.
            sessionReference: $handoff['clientMac'],
        );

        $authorized = $this->hotspot->authorize([
            'clientMac' => $handoff['clientMac'],
            'apMac' => $handoff['apMac'],
            'ssidName' => $handoff['ssidName'] ?? '',
            'radioId' => $handoff['radioId'] ?? '0',
            'site' => $handoff['site'] ?? '',
        ]);

        return redirect()->route('portal.omada.thanks', ['siteCode' => $site->site_code])
            ->with('authorized', $authorized)
            ->with('reference', substr($response->id, 0, 8));
    }

    public function thanks(string $siteCode): View
    {
        $site = Site::query()->where('site_code', $siteCode)->firstOrFail();

        return view('portal.omada-thanks', [
            'site' => $site,
            'authorized' => session('authorized', false),
            'reference' => session('reference'),
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

        $issuedAt = $this->issuedAtSeconds((string) $query['t']);

        if ($issuedAt === null) {
            return false;
        }

        // A stale handoff opens nothing: the user reconnects and Omada issues
        // a fresh one.
        $window = max(1, (int) config('portal.session_ttl_minutes', 15)) * 60;

        return abs(time() - $issuedAt) <= $window;
    }

    /**
     * TP-Link documents microseconds, but real controllers send seconds (seen
     * live). Accept seconds, milliseconds and microseconds by magnitude so a
     * unit change can never silently refuse every handoff.
     */
    private function issuedAtSeconds(string $t): ?float
    {
        $digits = ltrim(trim($t), '0');

        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        return match (true) {
            strlen($digits) <= 10 => (float) $digits,
            strlen($digits) <= 13 => ((float) $digits) / 1_000,
            default => ((float) $digits) / 1_000_000,
        };
    }
}
