<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gateway decision — simulator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
<div class="mx-auto max-w-2xl px-4 py-10">

    @if ($error)
        <div class="rounded-xl border border-red-300 bg-red-50 p-6">
            <h1 class="text-xl font-semibold text-red-900">Platform refused to answer</h1>
            <p class="mt-2 text-sm text-red-800">{{ $error }}</p>
            <p class="mt-3 text-sm text-red-800">
                A gateway treats this as a platform failure and applies its pre-approved fail-open policy.
            </p>
        </div>
    @else
        @if ($outcome['completed'])
            <div class="rounded-xl border border-green-300 bg-green-50 p-6 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-800">Gateway decision</p>
                <h1 class="mt-2 text-3xl font-bold text-green-900">ACCESS GRANTED</h1>
                <p class="mt-3 text-sm text-green-900">The platform confirms the survey was completed.</p>
            </div>
        @else
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Gateway decision</p>
                <h1 class="mt-2 text-3xl font-bold text-amber-900">ACCESS DENIED</h1>
                <p class="mt-3 text-sm text-amber-900">
                    Session status is <span class="font-mono">{{ $outcome['status'] }}</span>. The survey was not completed.
                </p>
            </div>
        @endif

        <dl class="mt-6 space-y-2 rounded-xl border border-slate-200 bg-white p-5 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Session</dt>
                <dd class="font-mono">{{ substr($outcome['session_reference'], 0, 8) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Completed at</dt>
                <dd>{{ $outcome['completed_at'] ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Policy</dt>
                <dd>{{ $outcome['policy'] }}</dd>
            </div>
            @if ($outcome['signature'])
                <div class="flex flex-col gap-1">
                    <dt class="text-slate-500">Signature (gateway can re-verify offline)</dt>
                    <dd class="font-mono text-xs break-all">{{ substr($outcome['signature'], 0, 32) }}…</dd>
                </div>
            @endif
        </dl>

        <p class="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-xs text-slate-600">
            @if ($integration->is_test_mode)
                This integration is in <strong>test mode</strong>. It cannot represent a production
                authorization decision (FR-CAP-007).
            @else
                This integration is live. In production the gateway must also apply its own
                approved outage policy when the platform is unreachable.
            @endif
        </p>
    @endif

    <p class="mt-6 text-center text-sm">
        <a class="underline" href="{{ route('simulator.index') }}">Back to the simulator</a>
    </p>
</div>
</body>
</html>