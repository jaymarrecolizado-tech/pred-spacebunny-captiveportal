@extends('public.layout')

@php($successHost = $successUrl ? (parse_url($successUrl, PHP_URL_HOST) ?: 'the next page') : '')

@section('title', 'Connecting you — '.$site->name)

@if ($authorized && $successUrl)
    @section('head')
        <meta http-equiv="refresh" content="5;url={{ $successUrl }}">
    @endsection
@endif

@section('content')
    <div class="card text-center">
        @if ($authorized)
            <p class="eyebrow text-emerald-600">Thank you &mdash; connecting you</p>

            <div class="mx-auto mt-4 grid h-24 w-24 place-items-center rounded-full bg-emerald-50 ring-1 ring-emerald-200">
                <span class="text-4xl font-bold tabular-nums text-emerald-700" id="count" aria-live="polite">5</span>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-slate-600" id="count-label">
                @if ($successUrl)
                    Taking you to <strong class="font-semibold text-slate-800">{{ $successHost }}</strong>&hellip;
                @else
                    Giving the Wi-Fi a moment to let you through&hellip;
                @endif
            </p>

            <div class="mt-4 hidden" id="final">
                <h1 class="text-xl font-semibold tracking-tight">You should now be online</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-600">
                    If nothing happens, close this window and open your browser &mdash;
                    your internet is already on.
                </p>
            </div>

            @if ($successUrl)
                <p class="mt-5">
                    <a href="{{ $successUrl }}" class="btn-primary">Continue to {{ $successHost }}</a>
                </p>
            @endif

            <noscript>
                <p class="mt-4 text-sm text-slate-600">Wait a few seconds, then continue below.</p>
            </noscript>

            @if ($successUrl)
                <script>
                    (function () {
                        var n = 5;
                        var el = document.getElementById('count');
                        var label = document.getElementById('count-label');
                        var final = document.getElementById('final');
                        var host = @json($successHost);
                        var destination = @json($successUrl);

                        var t = setInterval(function () {
                            n--;
                            if (n <= 0) {
                                clearInterval(t);

                                // Some captive mini-browsers only navigate once the
                                // authorization has had time to apply, so retry a few
                                // times before falling back to the button.
                                var attempts = 0;
                                var retry = setInterval(function () {
                                    attempts++;
                                    try {
                                        window.location.replace(destination);
                                    } catch (e) {
                                        window.location.href = destination;
                                    }
                                    if (attempts >= 3) {
                                        clearInterval(retry);
                                    }
                                }, 3000);

                                if (el) { el.textContent = '\u2713'; }
                                if (label) { label.textContent = 'Opening ' + host + '\u2026'; }
                                if (final) { final.classList.remove('hidden'); }
                            } else {
                                el.textContent = n;
                            }
                        }, 1000);
                    })();
                </script>
            @endif
        @else
            <span aria-hidden="true"
                  class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7">
                    <path d="m5 12.5 4.5 4.5L19 7.5"/>
                </svg>
            </span>

            <h1 class="mt-4 text-xl font-semibold tracking-tight">Thank you</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Your feedback for <strong class="font-semibold text-slate-800">{{ $site->name }}</strong> was recorded.
            </p>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                We couldn't connect you automatically this time &mdash; please disconnect and
                reconnect to the Wi-Fi, or ask staff for help.
            </p>
        @endif

        @if ($reference)
            <p class="mx-auto mt-4 inline-flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
                Reference <span class="font-mono text-slate-700">{{ $reference }}</span>
            </p>
        @endif
    </div>
@endsection
