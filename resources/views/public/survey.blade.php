@extends('public.layout')

@section('title', $site->name.' — Free Wi-Fi Feedback')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ $site->name }}
            @if ($site->municipality)
                <span class="font-normal normal-case">, {{ $site->municipality }}</span>
            @endif
        </p>
        <h1 class="mt-1 text-xl font-semibold">How is the Wi-Fi working today?</h1>
        <p class="mt-1 text-sm text-slate-600">
            This takes about a minute. Your answers are anonymous.
        </p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check the form:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $postUrl ?? route('site.survey.store', ['siteCode' => $site->site_code]) }}"
          class="survey-form mt-4 space-y-6">
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

        <button type="submit"
                class="min-h-12 w-full rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white
                       focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
            Send feedback
        </button>
    </form>
@endsection