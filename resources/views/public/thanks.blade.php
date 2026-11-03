@extends('public.layout')

@section('title', 'Thank you')

@section('content')
    <div class="card text-center">
        <span aria-hidden="true"
              class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" class="h-7 w-7">
                <path d="m5 12.5 4.5 4.5L19 7.5"/>
            </svg>
        </span>

        <h1 class="mt-4 text-xl font-semibold tracking-tight">Thank you</h1>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-600">
            Your feedback for <strong class="font-semibold text-slate-800">{{ $site->name }}</strong>
            was recorded anonymously.
        </p>

        @if ($reference)
            <p class="mx-auto mt-4 inline-flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
                Reference
                <span class="font-mono text-slate-700">{{ $reference }}</span>
            </p>
        @endif

        <p class="mt-5 text-sm text-slate-500">You can close this page and carry on using the Wi-Fi.</p>
    </div>
@endsection
