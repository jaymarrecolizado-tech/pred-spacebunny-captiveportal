@extends('public.layout')

@section('title', 'Feedback is not available')

@section('content')
    <div class="card">
        <span aria-hidden="true"
              class="grid h-11 w-11 place-items-center rounded-xl bg-slate-100 text-slate-500 ring-1 ring-slate-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <circle cx="12" cy="12" r="9"/>
                <path d="M8 12h8"/>
            </svg>
        </span>

        <h1 class="mt-3 text-xl font-semibold tracking-tight">Feedback is not available here</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            This page is not collecting feedback right now. The Wi-Fi service itself is unaffected,
            so please carry on using it as usual.
        </p>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">
            If you were sent here from a Wi-Fi login page, you can simply continue browsing.
        </p>
    </div>
@endsection
