@extends('public.layout')

@section('title', 'DICT Free Wi-Fi Customer Feedback')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">DICT Free Wi-Fi Customer Feedback</h1>
        <p class="mt-2 text-sm text-slate-600">
            This platform collects anonymous feedback from DICT Free Wi-Fi sites. Each site has its own
            feedback link or QR code, for example <span class="font-mono">/f/SITE-CODE</span>.
        </p>
        <p class="mt-3 text-sm text-slate-600">
            Site administrators sign in at <a class="underline" href="{{ url('/admin') }}">the admin panel</a>.
        </p>
    </div>
@endsection