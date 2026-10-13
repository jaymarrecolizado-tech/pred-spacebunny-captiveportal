@extends('public.layout')

@section('title', 'Thank you')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <h1 class="text-xl font-semibold">Thank you</h1>
        <p class="mt-2 text-sm text-slate-600">
            Your feedback for {{ $site->name }} was recorded anonymously.
        </p>
        @if ($reference)
            <p class="mt-3 text-xs text-slate-500">Reference: <span class="font-mono">{{ $reference }}</span></p>
        @endif
        <p class="mt-4 text-xs text-slate-500">You can close this page and continue using the Wi-Fi.</p>
    </div>
@endsection