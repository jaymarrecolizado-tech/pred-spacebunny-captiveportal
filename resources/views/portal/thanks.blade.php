@extends('public.layout')

@section('title', 'Thank you')

@section('content')
    <div class="rounded-xl border border-green-200 bg-green-50 p-6 text-center">
        <h1 class="text-xl font-semibold text-green-900">Thank you</h1>
        <p class="mt-2 text-sm text-green-900">Your feedback was recorded.</p>
        @if ($reference)
            <p class="mt-3 text-xs text-green-800">Reference: <span class="font-mono">{{ $reference }}</span></p>
        @endif
        <p class="mt-4 text-sm text-green-900">You are all set — you can close this page and browse.</p>
    </div>
@endsection