@extends('public.layout')

@section('title', 'Connecting you — '.$site->name)

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        @if ($authorized)
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Thank you — connecting you</p>
            <p class="mt-3 text-6xl font-bold tabular-nums" id="count" aria-live="polite">5</p>
            <p class="mt-2 text-sm text-slate-600" id="count-label">Giving the Wi-Fi a moment to let you through…</p>
            <div class="mt-4 hidden text-left" id="final">
                <h1 class="text-center text-xl font-semibold">You should now be online</h1>
                <p class="mt-2 text-center text-sm text-slate-600">
                    Try opening a website. If it doesn't load, disconnect and reconnect to {{ $site->name }}.
                </p>
            </div>
            <noscript>
                <p class="mt-4 text-sm text-slate-600">Wait a few seconds, then try opening a website.</p>
            </noscript>
            <script>
                (function () {
                    var n = 5;
                    var el = document.getElementById('count');
                    var label = document.getElementById('count-label');
                    var final = document.getElementById('final');
                    var t = setInterval(function () {
                        n--;
                        if (n <= 0) {
                            clearInterval(t);
                            el.classList.add('hidden');
                            label.classList.add('hidden');
                            final.classList.remove('hidden');
                        } else {
                            el.textContent = n;
                        }
                    }, 1000);
                })();
            </script>
        @else
            <h1 class="text-xl font-semibold">Thank you</h1>
            <p class="mt-2 text-sm text-slate-600">
                Your feedback for {{ $site->name }} was recorded.
            </p>
            <p class="mt-3 text-sm text-slate-600">
                We couldn't connect you automatically this time — please disconnect and
                reconnect to the Wi-Fi, or ask staff for help.
            </p>
        @endif
        @if ($reference)
            <p class="mt-3 text-xs text-slate-500">Reference: <span class="font-mono">{{ $reference }}</span></p>
        @endif
    </div>
@endsection