@extends('public.layout')

@php($successHost = $successUrl ? (parse_url($successUrl, PHP_URL_HOST) ?: 'the next page') : '')

@section('title', 'Connecting you — '.$site->name)

@if ($authorized && $successUrl)
    @section('head')
        <meta http-equiv="refresh" content="5;url={{ $successUrl }}">
    @endsection
@endif

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        @if ($authorized)
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Thank you &mdash; connecting you</p>
            <p class="mt-3 text-5xl font-bold tabular-nums" id="count" aria-live="polite">5</p>
            <p class="mt-3 text-center" id="count-label">
                @if ($successUrl)
                    Taking you to {{ $successHost }}&hellip;
                @else
                    Giving the Wi-Fi a moment to let you through&hellip;
                @endif
            </p>

            <div class="mt-4 hidden" id="final">
                <h1 class="text-center text-xl font-semibold">You should now be online</h1>
                <p class="mt-2 text-center text-sm text-slate-600">
                    If nothing happens, close this window and open your browser &mdash;
                    your internet is already on.
                </p>
            </div>

            @if ($successUrl)
                <p class="mt-4 text-center">
                    <a href="{{ $successUrl }}"
                       class="inline-block min-h-12 rounded-lg bg-slate-900 px-6 py-3 text-base font-semibold text-white">
                        Continue to {{ $successHost }}
                    </a>
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
            <h1 class="text-xl font-semibold">Thank you</h1>
            <p class="mt-2 text-sm text-slate-600">
                Your feedback for {{ $site->name }} was recorded.
            </p>
            <p class="mt-3 text-sm text-slate-600">
                We couldn't connect you automatically this time &mdash; please disconnect and
                reconnect to the Wi-Fi, or ask staff for help.
            </p>
        @endif

        @if ($reference)
            <p class="mt-3 text-xs text-slate-500">Reference: <span class="font-mono">{{ $reference }}</span></p>
        @endif
    </div>
@endsection