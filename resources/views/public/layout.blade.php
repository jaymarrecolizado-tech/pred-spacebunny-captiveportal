<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Free Wi-Fi Feedback')</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1d4ed8">
    {{-- Same colour in both schemes: the status bar should not flash white on a
         dark phone before the page paints. --}}
    <meta name="color-scheme" content="light">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('head')
    @stack('head')
</head>
<body class="flex min-h-dvh flex-col bg-slate-50 text-slate-900 antialiased">
    <a href="#main"
       class="sr-only rounded-lg bg-white px-4 py-2 font-semibold text-brand-700 ring-2 ring-brand-700 focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50">
        Skip to content
    </a>

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex w-full max-w-xl items-center gap-3 px-4 py-3">
            <span aria-hidden="true"
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-700 text-white shadow-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" class="h-5 w-5">
                    <path d="M4 9.5a12 12 0 0 1 16 0"/>
                    <path d="M7.5 13.5a7 7 0 0 1 9 0"/>
                    <circle cx="12" cy="18" r="1.4" fill="currentColor" stroke="none"/>
                </svg>
            </span>

            <div class="min-w-0">
                <p class="truncate text-[13px] font-semibold leading-tight tracking-tight">DICT Free Wi-Fi</p>
                <p class="truncate text-[11px] leading-tight text-slate-500">Customer feedback</p>
            </div>

            <span class="stat-chip ml-auto shrink-0 bg-emerald-50 text-emerald-700 ring-emerald-200">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                    <path d="m5 12 5 5L20 7"/>
                </svg>
                Anonymous
            </span>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-xl flex-1 px-4 pb-10 pt-5 sm:pt-8">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-xl flex-col gap-1 px-4 py-4 text-[11px] text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>No name, phone number or account is collected.</p>
            <p class="text-slate-400">DICT Free Wi-Fi for All</p>
        </div>
    </footer>
</body>
</html>
