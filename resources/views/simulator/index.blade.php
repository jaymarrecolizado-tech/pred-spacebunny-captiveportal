<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Gateway simulator — captive portal contract</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
<div class="mx-auto max-w-4xl px-4 py-8">
    <header class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Development only</p>
        <h1 class="text-2xl font-semibold">Gateway simulator</h1>
        <p class="mt-1 text-sm text-slate-600">
            Plays the part of the Wi-Fi gateway: it authenticates with its own credentials, asks the
            platform for a survey session, sends the user's browser there, then decides access from
            the platform's answer. No hardware required.
        </p>
    </header>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">1. A user joins the Wi-Fi</h2>
        <p class="mt-1 text-sm text-slate-600">The gateway holds the client captive and calls the init endpoint.</p>

        <form method="POST" action="{{ route('simulator.connect') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
            @csrf
            <label class="block text-sm">
                <span class="font-medium">Integration</span>
                <select name="integration_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                    @foreach ($integrations as $integration)
                        <option value="{{ $integration->id }}">
                            {{ $integration->public_identifier === '' ? '' : substr($integration->public_identifier, 0, 8) }}
                            — {{ $integration->site?->site_code ?? 'all sites' }}
                            @if ($integration->is_test_mode) (test) @endif
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="font-medium">Site</span>
                <select name="site_code" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                    @foreach ($sites as $site)
                        <option value="{{ $site->site_code }}">{{ $site->site_code }} — {{ $site->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end">
                <button type="submit" class="min-h-11 w-full rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">
                    Connect (opens survey)
                </button>
            </div>
        </form>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">2. Check whether the survey was completed</h2>
        <p class="mt-1 text-sm text-slate-600">
            The gateway must ask the platform. A public browser can never grant itself access.
        </p>

        <form method="POST" action="{{ route('simulator.status') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
            @csrf
            <label class="block text-sm">
                <span class="font-medium">Integration</span>
                <select name="integration_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                    @foreach ($integrations as $integration)
                        <option value="{{ $integration->id }}">{{ substr($integration->public_identifier, 0, 8) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm sm:col-span-2">
                <span class="font-medium">Session reference</span>
                <select name="session_reference" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                    @forelse ($sessions as $session)
                        <option value="{{ $session->id }}">
                            {{ substr($session->id, 0, 8) }} — {{ $session->site?->site_code }} ({{ $session->status->value }})
                        </option>
                    @empty
                        <option value="">No sessions yet</option>
                    @endforelse
                </select>
            </label>

            <div class="flex items-end sm:col-span-3">
                <button type="submit" class="min-h-11 rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">
                    Ask the platform
                </button>
            </div>
        </form>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">3. Negative test: tamper with a session</h2>
        <p class="mt-1 text-sm text-slate-600">
            Generates a URL with a substituted session reference. It must not open a survey.
        </p>

        <form method="POST" action="{{ route('simulator.tamper') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
            @csrf
            <label class="block text-sm sm:col-span-2">
                <span class="font-medium">Session reference</span>
                <select name="session_reference" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}">{{ substr($session->id, 0, 8) }} — {{ $session->site?->site_code }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="min-h-11 w-full rounded-lg border border-slate-900 px-4 py-2 font-semibold">
                    Build tampered URL
                </button>
            </div>
        </form>
    </section>

    <p class="mt-6 text-xs text-slate-500">
        Return to the <a class="underline" href="{{ url('/admin') }}">admin panel</a>.
    </p>
</div>
</body>
</html>