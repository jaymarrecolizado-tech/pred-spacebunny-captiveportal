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

    <div class="card">
        <span aria-hidden="true"
              class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <path d="M12 8v5M12 16h.01"/>
                <path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
            </svg>
        </span>

        <h1 class="mt-3 text-xl font-semibold leading-tight tracking-tight text-amber-900">
            {{ $reason['title'] }}
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-amber-900/90">{{ $reason['body'] }}</p>
        <p class="mt-3 border-t border-amber-200/70 pt-3 text-[13px] leading-relaxed text-amber-900/80">
            Your internet access is decided by the Wi-Fi gateway, not this page.
        </p>
    </div>
@endsection
