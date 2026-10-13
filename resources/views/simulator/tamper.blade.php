<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tamper test — simulator</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
<div class="mx-auto max-w-2xl px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Tampered session URL</h1>
        <p class="mt-2 text-sm text-slate-600">
            This URL carries a valid signature but a substituted session reference. Opening it must
            be refused, because the token is bound to one session (FR-CAP-004).
        </p>

        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Real session</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $session->id }}</p>

        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Tampered URL</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $tamperedUrl }}</p>

        <a href="{{ $tamperedUrl }}"
           class="mt-6 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
            Open the tampered URL (expect refusal)
        </a>
    </div>

    <p class="mt-6 text-center text-sm">
        <a class="underline" href="{{ route('simulator.index') }}">Back to the simulator</a>
    </p>
</div>
</body>
</html>