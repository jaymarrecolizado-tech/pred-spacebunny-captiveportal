@extends('public.layout')

@section('title', 'Session not available')

@section('content')
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
        <h1 class="text-xl font-semibold text-amber-900">This check-in link is no longer valid</h1>
        <p class="mt-2 text-sm text-amber-900">
            Sessions are short-lived and can only be used once. Reconnect to the Wi-Fi to get a new one.
        </p>
        <p class="mt-3 text-sm text-amber-900">
            Your internet access is decided by the Wi-Fi gateway, not this page.
        </p>
    </div>
@endsection