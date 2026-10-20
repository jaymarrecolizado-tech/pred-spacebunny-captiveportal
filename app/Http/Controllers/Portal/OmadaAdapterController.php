<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AttributionMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSurveyResponseRequest;
use App\Models\Site;
use App\Services\Portal\OmadaHotspotClient;
use App\Services\Responses\SubmitResponse;
use App\Services\Surveys\ResolveSurvey;
use App\Services\Surveys\SurveyTrigger;
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
        private readonly SurveyTrigger $trigger,
    ) {}

    public function show(string $siteCode): View|RedirectResponse
    {
        $site = Site::query()->where('site_code', $siteCode)->first();
        $handoff = request()->query();

        if ($site === null) {
            return $this->unavailable('unknown_location');
        }

        if (! $this->validHandoff($handoff)) {
            // Opened by hand, or a stale link: the person needs a different
            // instruction in each case, and "your link expired" is wrong and
            // alarming when no link ever existed.
            return $this->unavailable($this->hasHandoff($handoff) ? 'expired' : 'no_handoff');
        }

        // Recorded before the survey lookup: a handoff only arrives if the
        // controller has this location pointed at this platform, so it is proof
        // the location is live. The controller's API cannot report that, so it
        // is detected from real traffic rather than from a checkbox somebody has
        // to remember to tick.
        //
        // Deliberately before the survey check: "pointed at us" and "serving a
        // survey" are two different things, and an operator needs to see the
        // first even when the second is not set up yet.
        if ($site->portal_last_seen_at === null || $site->portal_last_seen_at->isPast()) {
            $site->forceFill(['portal_last_seen_at' => now()])->saveQuietly();
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return redirect()->route('portal.unavailable');
        }

        // Scheduled surveying: a device that answered recently, at a site set to
        // ask periodically or switched off, gets its access granted straight away
        // and never sees the survey. See SurveyTrigger for why a first-timer is
        // still always asked.
        if (! $this->trigger->shouldAsk($site, hash('sha256', $handoff['clientMac']))) {
            $authorized = $this->hotspot->authorize($this->client($handoff));

            return redirect()->route('portal.omada.thanks', ['siteCode' => $site->site_code])
                ->with('authorized', $authorized);
        }

        // Same view as the public survey, but posting back here so the
        // Omada handoff parameters survive to the authorize step.
        return view('public.survey', [
            'site' => $site,
            'resolved' => $resolved,
            'idempotencyKey' => (string) Str::uuid(),
            'postUrl' => route('portal.omada.store', ['siteCode' => $site->site_code])
                .'?'.http_build_query($handoff),
        ]);
    }

    public function store(string $siteCode, StoreSurveyResponseRequest $request): RedirectResponse
    {
        $site = Site::query()->where('site_code', $siteCode)->first();
        $handoff = $request->query();

        // The handoff is re-validated on POST: the form URL alone must never
        // be enough to submit through this path.
        if ($site === null || ! $this->validHandoff($handoff)) {
            return $this->unavailable('expired');
        }

        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return $this->unavailable('expired');
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

        $authorized = $this->hotspot->authorize($this->client($handoff));

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
            'successUrl' => config('portal.success_url'),
        ]);
    }

    /**
     * Whether the request carries a handoff at all.
     *
     * A request with no device details is not an expired session, it is an
     * address opened by hand. The two need different instructions.
     *
     * @param  array<string, mixed>  $query
     */
    private function hasHandoff(array $query): bool
    {
        foreach (['clientMac', 'apMac', 'gatewayMac', 't'] as $key) {
            if (isset($query[$key]) && trim((string) $query[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Send the person to the page that explains what actually went wrong.
     */
    private function unavailable(string $reason): RedirectResponse
    {
        return redirect()->route('portal.unavailable')->with('portalReason', $reason);
    }

    /**
     * The connecting device, in one shape.
     *
     * The controller names the same things differently depending on the kind
     * of portal it is running:
     *
     *  - a portal bound to an SSID sends apMac, ssidName and radioId;
     *  - a portal bound to a network sends gatewayMac and vid, and no SSID
     *    name at all, because the device is behind a gateway rather than
     *    attached to one.
     *
     * Both are the same handoff, so both are read the same way here. Getting
     * this wrong is invisible until a real phone is refused at the door.
     *
     * @param  array<string, mixed>  $query
     * @return array{clientMac: string, apMac: string, ssidName: string, radioId: string, site: string}
     */
    private function client(array $query): array
    {
        return [
            'clientMac' => (string) ($query['clientMac'] ?? ''),
            'apMac' => (string) ($query['apMac'] ?? $query['gatewayMac'] ?? ''),
            'ssidName' => (string) ($query['ssidName'] ?? ''),
            'radioId' => (string) ($query['radioId'] ?? $query['vid'] ?? '0'),
            'site' => (string) ($query['site'] ?? ''),
        ];
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
        foreach (['clientMac', 't'] as $key) {
            if (! isset($query[$key]) || trim((string) $query[$key]) === '') {
                return false;
            }
        }

        // The access point is named differently by the two portal styles. One
        // of them has to be there, or there is no device to authorize.
        if (blank(trim((string) ($query['apMac'] ?? $query['gatewayMac'] ?? '')))) {
            return false;
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
