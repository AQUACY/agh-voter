@extends('layouts.app')

@section('title', 'Paper or online')

@section('content')
<div class="mx-auto max-w-3xl">
    <x-voter-progress current="method" />
    <p class="kicker" data-enter>At the Electoral Commission table</p>
    <div class="panel mt-4 rounded-[28px] p-8" data-enter>
        <p class="text-sm text-[var(--muted)]">{{ $voter->name }} · {{ $voter->staff_id }}</p>
        <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">How will you vote?</h1>
        <p class="lede mt-4">The official is watching this step. Choose once. Paper prints now and immediately closes online voting. Online opens the secret digital ballot.</p>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('voter.method.paper') }}">
                @csrf
                <button class="flex min-h-44 w-full flex-col rounded-[24px] bg-[var(--forest)] px-6 py-8 text-left text-[var(--ivory)] transition hover:bg-[var(--forest-deep)]">
                    <span class="kicker">Manual</span>
                    <span class="display mt-3 text-4xl">Print paper ballot</span>
                    <span class="mt-3 text-sm leading-7 text-[#e7e5e4]">The printer issues the official sheets. This Staff ID cannot vote online after that. Stamp with your thumb and drop the sheets in the box.</span>
                </button>
            </form>

            <form method="POST" action="{{ route('voter.method.online') }}">
                @csrf
                <button class="flex min-h-44 w-full flex-col rounded-[24px] bg-[var(--paper)] px-6 py-8 text-left ring-1 ring-[color-mix(in_srgb,var(--gold)_35%,transparent)] transition hover:ring-[var(--gold)]">
                    <span class="kicker">Digital</span>
                    <span class="display mt-3 text-4xl text-[var(--ink)]">Vote online</span>
                    <span class="mt-3 text-sm leading-7 text-[var(--muted)]">Cast a secret ballot on this screen. No paper sheet is issued, and this Staff ID cannot vote again.</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
