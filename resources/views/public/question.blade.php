{{-- One question from the survey schema.

     Shared by the captive portal form and the site link form, so the two can
     never ask the public different things.

     A question may carry `show_when`, meaning it is only shown, and only
     stored, when another answer says so. --}}
@php
    $key = $question['key'];
    $old = old("answers.$key");
    $describedBy = $key.'-help';
    $type = $question['type'] ?? null;
    $when = $question['show_when'] ?? null;
@endphp

@if ($when)
    @push('head')
        <style>
            /* CSS only. A captive portal phone can be slow or old, and a form
               that needs JavaScript to reveal a box is a form that quietly
               loses answers. Browsers that cannot do this keep the box
               visible, which is the safe way to be wrong. */
            @supports selector(:has(*)) {
                .q-{{ $key }} { display: none; }
                .survey-form:has(input[name="answers[{{ $when['question'] }}]"][value="{{ $when['value'] }}"]:checked) .q-{{ $key }} { display: block; }
            }
        </style>
    @endpush
@endif

<div class="{{ $when ? 'q-'.$key : '' }}">
    @if ($type === 'info')
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            {{ $question['label'] ?? '' }}
        </div>
    @elseif ($type === 'rating')
        <fieldset aria-describedby="{{ $describedBy }}">
            <legend class="text-base font-medium">
                {{ $question['label'] }}
                @if ($question['required'] ?? false)
                    <span class="text-red-700" aria-hidden="true">*</span>
                @endif
            </legend>
            <p id="{{ $describedBy }}" class="mt-1 text-sm text-slate-600">{{ $question['help'] ?? '' }}</p>
            @php $rMin = $question['min'] ?? 1; $rMax = $question['max'] ?? 5; @endphp
            <div class="star-rating mt-3">
                @for ($i = $rMax; $i >= $rMin; $i--)
                    <input type="radio" id="{{ $key }}-{{ $i }}" name="answers[{{ $key }}]"
                           value="{{ $i }}" aria-label="{{ $i }} out of {{ $rMax }} stars"
                           @checked((string) $old === (string) $i)
                           @if ($question['required'] ?? false) required @endif>
                    <label for="{{ $key }}-{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                           aria-hidden="true">★</label>
                @endfor
            </div>
            <p class="mt-1 text-xs text-slate-500">Tap a star: {{ $rMin }} = poor, {{ $rMax }} = excellent.</p>
        </fieldset>
    @elseif ($type === 'single_choice')
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
    @elseif ($type === 'short_text')
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
</div>
