<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Free Wi-Fi Feedback')</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#103a53">
    {{-- Same colour in both schemes: the status bar should not flash white on a
         dark phone before the page paints. --}}
    <meta name="color-scheme" content="light">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('head')
    @stack('head')
</head>
<body class="flex min-h-dvh flex-col bg-[#f3f6f8] text-slate-900 antialiased">
    <a href="#main"
       class="sr-only rounded-lg bg-white px-4 py-2 font-semibold text-brand-800 ring-2 ring-brand-800 focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50">
        Skip to content
    </a>

    <header class="sticky top-0 z-30 border-b border-brand-900/30 bg-brand-900 text-white">
        <div class="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 py-3">
            <span aria-hidden="true"
                  class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 ring-1 ring-white/20">
                <svg viewBox="0 0 32 32" fill="none" class="h-6 w-6 text-white" aria-hidden="true">
                    <path d="M4.5 12a19 19 0 0 1 23 0" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                    <path d="M9 17.6a12.5 12.5 0 0 1 14 0" stroke="#d9a437" stroke-width="2.6" stroke-linecap="round"/>
                    <path d="M13.5 23.1a5.6 5.6 0 0 1 5 0" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                    <circle cx="16" cy="27" r="1.55" fill="currentColor"/>
                </svg>
            </span>

            <div class="min-w-0">
                <p class="truncate text-sm font-semibold leading-tight tracking-tight text-white">DICT Free Wi-Fi</p>
                <p class="truncate text-[10px] font-medium uppercase leading-tight tracking-[0.12em] text-white/65">For All · Customer feedback</p>
            </div>

            <span class="stat-chip ml-auto shrink-0 bg-emerald-400/15 text-emerald-200 ring-emerald-300/25">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                    <path d="m5 12 5 5L20 7"/>
                </svg>
                Anonymous
            </span>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-2xl flex-1 px-4 pb-12 pt-6 sm:pt-9">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-2 px-4 py-4 text-[11px] text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p class="max-w-sm">Anonymous. No name, phone number or account is collected.</p>
            <p class="font-semibold text-brand-800">Department of Information and Communications Technology</p>
        </div>
    </footer>
</body>
</html>
