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
    $required = $question['required'] ?? false;
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

<div class="{{ $when ? 'q-'.$key : '' }} card">
    @if ($type === 'info')
        <div class="flex gap-3 rounded-xl bg-brand-50 p-4 text-sm leading-relaxed text-brand-800 ring-1 ring-brand-100">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 11v5M12 8h.01"/>
            </svg>
            <p>{{ $question['label'] ?? '' }}</p>
        </div>

    @elseif ($type === 'rating')
        <fieldset aria-describedby="{{ $describedBy }}">
            <legend class="field-legend">
                {{ $question['label'] }}
                @if ($required)
                    <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only"> (required)</span>
                @endif
            </legend>
            <p id="{{ $describedBy }}" class="field-help">{{ $question['help'] ?? '' }}</p>

            @php $rMin = $question['min'] ?? 1; $rMax = $question['max'] ?? 5; @endphp

            <div class="star-rating mt-3">
                @for ($i = $rMax; $i >= $rMin; $i--)
                    <input type="radio" id="{{ $key }}-{{ $i }}" name="answers[{{ $key }}]"
                           value="{{ $i }}" aria-label="{{ $i }} out of {{ $rMax }} stars"
                           @checked((string) $old === (string) $i)
                           @if ($required) required @endif>
                    <label for="{{ $key }}-{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                           aria-hidden="true">★</label>
                @endfor
            </div>

            <div class="mt-1 flex items-baseline justify-between text-[11px] font-medium text-slate-500">
                <span>{{ $rMin }} &middot; Poor</span>
                <span>{{ $rMax }} &middot; Excellent</span>
            </div>
        </fieldset>

    @elseif ($type === 'single_choice')
        <fieldset aria-describedby="{{ $describedBy }}">
            <legend class="field-legend">
                {{ $question['label'] }}
                @if ($required)
                    <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only"> (required)</span>
                @endif
            </legend>
            <p id="{{ $describedBy }}" class="field-help">{{ $question['help'] ?? '' }}</p>

            <div class="mt-3 space-y-2">
                @foreach ($question['options'] ?? [] as $option)
                    <label class="choice">
                        <input type="radio" name="answers[{{ $key }}]" value="{{ $option['value'] }}"
                               class="h-5 w-5 shrink-0 accent-brand-700"
                               @checked((string) $old === (string) $option['value'])>
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

    @elseif ($type === 'short_text')
        <div>
            <label for="{{ $key }}" class="field-legend">
                {{ $question['label'] }}
                @if ($required)
                    <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only"> (required)</span>
                @endif
            </label>
            <p id="{{ $describedBy }}" class="field-help">{{ $question['help'] ?? '' }}</p>

            <textarea id="{{ $key }}" name="answers[{{ $key }}]" rows="3"
                      maxlength="{{ $question['max_length'] ?? 500 }}"
                      class="mt-2 w-full resize-y rounded-xl border border-slate-200 bg-white p-3 text-[15px] leading-relaxed
                             placeholder:text-slate-400 focus:border-brand-600 focus:outline-2 focus:outline-offset-0
                             focus:outline-brand-600"
                      aria-describedby="{{ $describedBy }}">{{ $old }}</textarea>
            <p class="field-hint">Up to {{ $question['max_length'] ?? 500 }} characters. Written answers are read by DICT staff.</p>
        </div>
    @endif
</div>
