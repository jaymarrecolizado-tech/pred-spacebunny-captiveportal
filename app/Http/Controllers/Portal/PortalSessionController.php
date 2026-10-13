<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AttributionMethod;
use App\Http\Controllers\Controller;
use App\Services\Portal\PortalSessionService;
use App\Services\Responses\SubmitResponse;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The browser half: the survey a validated session is allowed to see.
 */
class PortalSessionController extends Controller
{
    public function __construct(
        private readonly PortalSessionService $sessions,
        private readonly ResolveSurvey $resolveSurvey,
        private readonly SubmitResponse $submitResponse,
    ) {}

    public function show(string $session, Request $request): View|RedirectResponse
    {
        $portalSession = $this->sessions->resolve($request->query('token'), $session);

        if ($portalSession === null || $portalSession->isCompleted()) {
            return $this->refuse();
        }

        $resolved = $this->resolveSurvey->forSite($portalSession->site);

        if ($resolved === null) {
            return $this->refuse();
        }

        return view('portal.session', [
            'portalSession' => $portalSession,
            'site' => $portalSession->site,
            'resolved' => $resolved,
            'token' => $request->query('token'),
            'idempotencyKey' => request()->old('idempotency_key') ?? (string) Str::uuid(),
        ]);
    }

    public function store(string $session, Request $request): RedirectResponse
    {
        $portalSession = $this->sessions->resolve($request->query('token'), $session);

        if ($portalSession === null || $portalSession->isCompleted()) {
            return $this->refuse();
        }

        $resolved = $this->resolveSurvey->forSite($portalSession->site);

        if ($resolved === null) {
            return $this->refuse();
        }

        $answers = (array) $request->input('answers', []);
        $clean = $this->submitResponse->validateAnswers($resolved->version, $answers);

        $response = $this->submitResponse->handle(
            site: $portalSession->site,
            resolved: $resolved,
            answers: $clean,
            // FR-RESP-005/006: this is the only path that may claim verified
            // attribution, because the session was validated against a secret
            // the public browser never has.
            attribution: AttributionMethod::CaptiveVerified,
            idempotencyKey: $request->input('idempotency_key'),
            sessionReference: $portalSession->id,
        );

        $this->sessions->complete($portalSession, $response->id);

        return redirect()->route('portal.thanks', ['session' => $portalSession->id]);
    }

    public function thanks(string $session): View
    {
        return view('portal.thanks', [
            'reference' => substr($session, 0, 8),
        ]);
    }

    /**
     * FR-CAP-008: an unusable token never leaves the user at a dead end — the
     * gateway's own fail-open policy decides access from here.
     */
    private function refuse(): RedirectResponse
    {
        return redirect()->route('portal.unavailable');
    }
}
