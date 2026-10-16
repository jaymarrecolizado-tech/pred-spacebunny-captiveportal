<?php

namespace App\Providers;

use App\Services\Omada\OmadaOpenApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registered here rather than in a dedicated provider: this one is
        // already guaranteed to boot, and a container binding that lives in a
        // provider which fails to register fails silently - the symptom is an
        // "unresolvable dependency" error at call time, far from the cause.
        //
        // Values come from config/omada.php, not env() directly: env() returns
        // null once config is cached, which is what silently broke this in
        // production while working perfectly locally.
        $this->app->singleton(OmadaOpenApiClient::class, fn () => new OmadaOpenApiClient(config('omada', [])));
    }

    public function boot(): void
    {
        // FR-RESP-010: proportional public-endpoint limits, keyed by IP and site
        // so one busy location cannot exhaust the quota for the rest.
        RateLimiter::for('public-submit', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip().'|'.$request->route('siteCode')));

        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // FR-CAP-004: a gateway gets a budget, not the whole internet.
        RateLimiter::for('integration', fn (Request $request) => Limit::perMinute(120)
            ->by((string) $request->header('X-Integration-Id')));

        // Trust proxies for correct client IPs behind the VPS web server only.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
