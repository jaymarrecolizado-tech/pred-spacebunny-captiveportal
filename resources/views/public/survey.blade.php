@extends('public.layout')

@section('title', $site->name.' — Free Wi-Fi Feedback')

@section('content')
    <section class="card">
        <p class="eyebrow">
            {{ $site->name }}@if ($site->municipality) &middot; {{ $site->municipality }}@endif
        </p>
        <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-balance">
            How is the Wi-Fi working today?
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            About a minute, and it helps us fix the slow spots. Your answers are anonymous.
        </p>
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
