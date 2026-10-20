@extends('public.layout')

@section('title', 'Session not available')

@section('content')
    @php
        // The reason changes what the person should do next, so it is worth
        // saying plainly. Someone who opened the address by hand needs to
        // know it opens by itself when the device connects; someone whose
        // session expired needs to reconnect.
        $reasons = [
            'no_handoff' => [
                'title' => 'This page opens by itself when you connect',
                'body' => 'You are seeing it because the address was opened without a connection. Join the Wi-Fi network and your phone will open the check-in by itself.',
            ],
            'unknown_location' => [
                'title' => 'This location is not set up yet',
                'body' => 'The Wi-Fi system pointed us at a location this platform does not know about. Check the address in the portal configuration.',
            ],
            'expired' => [
                'title' => 'This check-in link is no longer valid',
                'body' => 'Sessions are short-lived and can only be used once. Reconnect to the Wi-Fi to get a new one.',
            ],
        ];

        $reason = $reasons[session('portalReason')] ?? $reasons['expired'];
    @endphp

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
        <h1 class="text-xl font-semibold text-amber-900">{{ $reason['title'] }}</h1>
        <p class="mt-2 text-sm text-amber-900">
            {{ $reason['body'] }}
        </p>
        <p class="mt-3 text-sm text-amber-900">
            Your internet access is decided by the Wi-Fi gateway, not this page.
        </p>
    </div>
@endsection