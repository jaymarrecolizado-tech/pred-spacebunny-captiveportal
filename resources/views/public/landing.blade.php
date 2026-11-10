@extends('public.layout')

@section('title', 'DICT Free Wi-Fi Feedback')

@section('content')
    <section class="overflow-hidden rounded-3xl border border-brand-900 bg-brand-900 text-white shadow-xl">
        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
            <div class="absolute inset-y-0 right-0 hidden w-1/2 bg-[radial-gradient(circle_at_40%_40%,rgba(217,164,55,.18),transparent_55%)] sm:block"></div>
            <div class="relative max-w-xl">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#d9a437]">DICT Free Wi-Fi for All</p>
                <h1 class="mt-3 text-3xl font-bold leading-[1.08] tracking-tight sm:text-4xl">
                    Public feedback,<br>built for better Wi-Fi.
                </h1>
                <p class="mt-4 max-w-md text-base leading-relaxed text-white/80">
                    Every location gets a feedback link and an anonymous experience—so DICT can spot weak coverage and fix it.
                </p>

                <div class="mt-6 grid max-w-md grid-cols-3 gap-2 text-center text-[11px] text-white/75">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3"><p class="text-lg font-bold text-white">1</p>Connect</div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3"><p class="text-lg font-bold text-white">2</p>Answer</div>
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3"><p class="text-lg font-bold text-white">3</p>Help improve</div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="card">
            <p class="eyebrow">For visitors</p>
            <h2 class="mt-2 text-lg font-semibold tracking-tight text-brand-900">Share your experience</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Your site’s QR poster or link opens this same anonymous form. No sign-up and no personal details are required.
            </p>
            <p class="mt-4 font-mono text-[11px] text-slate-500">Example: /f/SITE-CODE</p>
        </div>

        <div class="card">
            <p class="eyebrow">For administrators</p>
            <h2 class="mt-2 text-lg font-semibold tracking-tight text-brand-900">Operate the network</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Sign in to review feedback, performance and operational actions across all locations.
            </p>
            <a href="{{ url('/admin') }}"
               class="btn-primary mt-4">Open the admin panel</a>
        </div>
    </section>

    <section class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-[12px] leading-relaxed text-slate-600 sm:flex-row sm:items-center sm:justify-between">
        <p><strong class="text-brand-900">Privacy by design.</strong> No name, phone number or account is collected.</p>
        <p class="shrink-0 font-semibold text-brand-800">DICT · Free Wi-Fi for All</p>
    </section>
@endsection
