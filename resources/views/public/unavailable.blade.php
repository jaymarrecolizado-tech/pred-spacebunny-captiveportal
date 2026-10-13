@extends('public.layout')

@section('title', 'Feedback is not available')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Feedback is not available here</h1>
        <p class="mt-2 text-sm text-slate-600">
            This page is not collecting feedback right now. The Wi-Fi service itself is unaffected — please
            carry on using it as usual.
        </p>
        <p class="mt-3 text-sm text-slate-600">
            If you were redirected here from a Wi-Fi login page, you can continue browsing.
        </p>
    </div>
@endsection