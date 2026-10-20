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
          class="survey-form mt-4 space-y-6">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        @foreach ($resolved->version->questions() as $question)
            @include('public.question', ['question' => $question])
        @endforeach

        <button type="submit"
                class="min-h-12 w-full rounded-lg bg-slate-900 px-4 py-3 text-base font-semibold text-white focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
            Submit and continue
        </button>
    </form>
@endsection