@extends('public.layout')

@section('title', 'DICT Free Wi-Fi Customer Feedback')

@section('content')
    <div class="card">
        <p class="eyebrow">DICT Free Wi-Fi for All</p>
        <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-balance">
            Customer feedback
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            This platform collects anonymous feedback from DICT Free Wi-Fi sites. Each site has its
            own feedback link or QR code, for example <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[12px] text-slate-700">/f/SITE-CODE</span>.
        </p>

        <hr class="my-4 border-slate-200">

        <p class="text-sm text-slate-600">
            Site administrators sign in at
            <a class="font-semibold text-brand-700 underline decoration-brand-200 underline-offset-2 hover:decoration-brand-700"
               href="{{ url('/admin') }}">the admin panel</a>.
        </p>
    </div>
@endsection
