@extends('public.layout')

@section('title', $site->name.' — Free Wi-Fi Feedback')

@section('content')
    <section class="overflow-hidden rounded-2xl border border-brand-900 bg-brand-800 text-white shadow-lg">
        <div class="flex items-center gap-5 px-5 py-6 sm:px-6">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" class="h-8 w-8" aria-hidden="true">
                    <path d="M4.5 10.5a11 11 0 0 1 15 0"/>
                    <path d="M8 14.6a6.3 6.3 0 0 1 8 0"/>
                    <circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#d9a437]">{{ $site->name }}</p>
                <h1 class="mt-1.5 text-2xl font-bold leading-[1.1] tracking-tight sm:text-3xl">
                    How is the Wi-Fi today?
                </h1>
                <p class="mt-1.5 text-sm leading-relaxed text-white/75">
                    About a minute. Your answer helps fix the slow spots for everyone.
                </p>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div role="alert" class="alert-error mt-4">
            <p class="font-semibold">Please check the form:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $postUrl ?? route('site.survey.store', ['siteCode' => $site->site_code]) }}"
          class="survey-form mt-4 space-y-4">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        {{-- Honeypot: hidden from people, tempting to bots. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        @foreach ($resolved->version->questions() as $question)
            @include('public.question', ['question' => $question])
        @endforeach

        {{-- Pinned to the bottom of the screen on a phone so the answer is never
             a scroll away, and back to normal flow on a tablet or desktop. --}}
        <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-slate-200 bg-slate-50/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
            <button type="submit" class="btn-primary">Send feedback</button>
            <p class="mt-2 text-center text-[11px] text-slate-500 sm:hidden">
                You can close this page after sending.
            </p>
        </div>
    </form>
@endsection
