<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
