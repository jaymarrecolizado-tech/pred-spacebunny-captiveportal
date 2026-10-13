<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Support\QrCode;
use App\Http\Requests\StoreSurveyResponseRequest;
use App\Models\Site;
use App\Services\Responses\SubmitResponse;
use App\Services\Surveys\ResolveSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicSurveyController extends Controller
{
    public function __construct(
        private readonly ResolveSurvey $resolveSurvey,
        private readonly SubmitResponse $submitResponse,
    ) {}

    public function show(string $siteCode): View|RedirectResponse
    {
        $site = $this->findSite($siteCode);
        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return redirect()->route('site.unavailable');
        }

        return view('public.survey', [
            'site' => $site,
            'resolved' => $resolved,
            // Regenerated per page load; a refresh after an error gets a new key
            // only when the previous attempt was never accepted.
            'idempotencyKey' => request()->old('idempotency_key') ?? (string) Str::uuid(),
        ]);
    }

    public function store(string $siteCode, StoreSurveyResponseRequest $request): RedirectResponse
    {
        $site = $this->findSite($siteCode);
        $resolved = $this->resolveSurvey->forSite($site);

        if ($resolved === null) {
            return redirect()->route('site.unavailable');
        }

        $response = $this->submitResponse->handle(
            site: $site,
            resolved: $resolved,
            answers: $request->validated('answers'),
            attribution: $site->publicAttributionMethod(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return redirect()->route('site.thanks', [
            'siteCode' => $site->site_code,
            'reference' => substr($response->id, 0, 8),
        ]);
    }

    public function thanks(string $siteCode, Request $request): View
    {
        $site = $this->findSite($siteCode);

        return view('public.thanks', [
            'site' => $site,
            'reference' => $request->query('reference'),
        ]);
    }

    /**
     * Printable QR poster for the site (Plan section 8: printable QR posters).
     */
    public function poster(string $siteCode): View
    {
        $site = $this->findSite($siteCode);

        return view('public.poster', [
            'site' => $site,
            'qr' => QrCode::svg(route('site.survey', ['siteCode' => $site->site_code], absolute: true)),
            'url' => route('site.survey', ['siteCode' => $site->site_code], absolute: true),
        ]);
    }

    public function unavailable(): View
    {
        return view('public.unavailable');
    }

    private function findSite(string $siteCode): Site
    {
        return Site::query()->where('site_code', $siteCode)->firstOrFail();
    }
}
