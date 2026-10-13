@extends('public.layout')

@section('title', $site->name.' — Free Wi-Fi Feedback')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ $site->name }}
            @if ($site->municipality)
                <span class="font-normal normal-case">, {{ $site->municipality }}</span>
            @endif
        </p>
        <h1 class="mt-1 text-xl font-semibold">How is the Wi-Fi working today?</h1>
        <p class="mt-1 text-sm text-slate-600">
            This takes about a minute. Your answers are anonymous.
        </p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check the form:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('site.survey.store', ['siteCode' => $site->site_code]) }}"
          class="mt-4 space-y-6">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        {{-- Honeypot: hidden from people, tempting to bots. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        @foreach ($resolved->version->questions() as $question)
            @php
                $key = $question['key'];
                $old = old("answers.$key");
                $describedBy = $key.'-help';
            @endphp

            @if (($question['type'] ?? null) === 'info')
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    {{ $question['label'] ?? '' }}
                </div>
            @elseif (($question['type'] ?? null) === 'rating')
                <fieldset aria-describedby="{{ $describedBy }}">
                    <legend class="text-base font-medium">
                        {{ $question['label'] }}
                        @if ($question['required'] ?? false)
                            <span class="text-red-700" aria-hidden="true">*</span>
                        @endif
                    </legend>
                    <p id="{{ $describedBy }}" class="mt-1 text-sm text-slate-600">{{ $question['help'] ?? '' }}</p>
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @for ($i = $question['min'] ?? 1; $i <= $question['max'] ?? 5; $i++)
                            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border border-slate-300 text-lg font-semibold
                                        focus-within:ring-2 focus-within:ring-slate-900 focus-within:ring-offset-2
                                        {{ (string) $old === (string) $i ? 'border-slate-900 bg-slate-900 text-white' : 'bg-white' }}">
                                <input type="radio" name="answers[{{ $key }}]" value="{{ $i }}" class="sr-only"
                                       @checked((string) $old === (string) $i)>
                                <span>{{ $i }}</span>
                            </label>
                        @endfor
                    </div>
                </fieldset>
            @elseif (($question['type'] ?? null) === 'single_choice')
                <fieldset aria-describedby="{{ $describedBy }}">
                    <legend class="text-base font-medium">
                        {{ $question['label'] }}
                        @if ($question['required'] ?? false)
                            <span class="text-red-700" aria-hidden="true">*</span>
                        @endif
                    </legend>
                    <p id="{{ $describedBy }}" class="mt-1 text-sm text-slate-600">{{ $question['help'] ?? '' }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($question['options'] ?? [] as $option)
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-300 bg-white px-4
                                        focus-within:ring-2 focus-within:ring-slate-900 focus-within:ring-offset-2
                                        {{ (string) $old === (string) $option['value'] ? 'border-slate-900' : '' }}">
                                <input type="radio" name="answers[{{ $key }}]" value="{{ $option['value'] }}" class="h-5 w-5"
                                       @checked((string) $old === (string) $option['value'])>
                                <span>{{ $option['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @elseif (($question['type'] ?? null) === 'short_text')
                <div>
                    <label for="{{ $key }}" class="block text-base font-medium">
                        {{ $question['label'] }}
                        @if ($question['required'] ?? false)
                            <span class="text-red-700" aria-hidden="true">*</span>
                        @endif
                    </label>
                    <p id="{{ $describedBy }}" class="mt-1 text-sm text-slate-600">{{ $question['help'] ?? '' }}</p>
                    <textarea id="{{ $key }}" name="answers[{{ $key }}]" rows="3"
                              maxlength="{{ $question['max_length'] ?? 500 }}"
                              class="mt-2 w-full rounded-lg border border-slate-300 p-3 focus:ring-2 focus:ring-slate-900 focus:ring-offset-2"
                              aria-describedby="{{ $describedBy }}">{{ $old }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Up to {{ $question['max_length'] ?? 500 }} characters.</p>
                </div>
            @endif
        @endforeach

        <button type="submit"
                class="min-h-12 w-full rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white
                       focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
            Send feedback
        </button>
    </form>
@endsection