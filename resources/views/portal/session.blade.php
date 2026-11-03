@extends('public.layout')

@section('title', 'Wi-Fi survey — '.$site->name)

@section('content')
    <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5">
        <p class="eyebrow text-brand-700">Free Wi-Fi check-in</p>
        <h1 class="mt-1.5 text-xl font-semibold leading-tight tracking-tight text-brand-900">
            {{ $site->name }}
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-brand-900/90">
            You are connected to this site's Wi-Fi. One quick question helps us make it better.
        </p>
    </div>

    @if ($errors->any())
        <div role="alert" class="alert-error mt-4">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.session.store', ['session' => $portalSession->id, 'token' => $token]) }}"
          class="survey-form mt-4 space-y-4">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        @foreach ($resolved->version->questions() as $question)
            @include('public.question', ['question' => $question])
        @endforeach

        <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t border-slate-200 bg-slate-50/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
            <button type="submit" class="btn-primary">Submit and continue</button>
        </div>
    </form>
@endsection
