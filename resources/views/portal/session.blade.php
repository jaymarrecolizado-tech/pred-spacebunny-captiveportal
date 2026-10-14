@extends('public.layout')

@section('title', 'Wi-Fi survey — '.$site->name)

@section('content')
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Free Wi-Fi check-in</p>
        <h1 class="mt-1 text-xl font-semibold">{{ $site->name }}</h1>
        <p class="mt-1 text-sm text-blue-900">
            You are connected to this site's Wi-Fi. Please answer one quick question so we can improve it.
        </p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.session.store', ['session' => $portalSession->id, 'token' => $token]) }}"
          class="mt-4 space-y-6">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        @foreach ($resolved->version->questions() as $question)
            @php
                $key = $question['key'];
                $old = old("answers.$key");
            @endphp

            @if (($question['type'] ?? null) === 'info')
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    {{ $question['label'] ?? '' }}
                </div>
            @elseif (($question['type'] ?? null) === 'rating')
                <fieldset>
                    <legend class="text-base font-medium">
                        {{ $question['label'] }}
                        @if ($question['required'] ?? false)<span class="text-red-700" aria-hidden="true">*</span>@endif
                    </legend>
                    @if (! empty($question['help']))
                        <p class="mt-1 text-sm text-slate-600">{{ $question['help'] }}</p>
                    @endif
                    @php $rMin = $question['min'] ?? 1; $rMax = $question['max'] ?? 5; @endphp
                    <div class="star-rating mt-3">
                        @for ($i = $rMax; $i >= $rMin; $i--)
                            <input type="radio" id="portal-{{ $key }}-{{ $i }}" name="answers[{{ $key }}]"
                                   value="{{ $i }}" aria-label="{{ $i }} out of {{ $rMax }} stars"
                                   @checked((string) $old === (string) $i)
                                   @if ($question['required'] ?? false) required @endif>
                            <label for="portal-{{ $key }}-{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                                   aria-hidden="true">★</label>
                        @endfor
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Tap a star: {{ $rMin }} = poor, {{ $rMax }} = excellent.</p>
                </fieldset>
            @elseif (($question['type'] ?? null) === 'single_choice')
                <fieldset>
                    <legend class="text-base font-medium">
                        {{ $question['label'] }}
                        @if ($question['required'] ?? false)<span class="text-red-700" aria-hidden="true">*</span>@endif
                    </legend>
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
                    <label for="{{ $key }}" class="block text-base font-medium">{{ $question['label'] }}</label>
                    <textarea id="{{ $key }}" name="answers[{{ $key }}]" rows="3"
                              maxlength="{{ $question['max_length'] ?? 500 }}"
                              class="mt-2 w-full rounded-lg border border-slate-300 p-3 focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">{{ $old }}</textarea>
                </div>
            @endif
        @endforeach

        <button type="submit"
                class="min-h-12 w-full rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
            Submit and continue
        </button>
    </form>
@endsection