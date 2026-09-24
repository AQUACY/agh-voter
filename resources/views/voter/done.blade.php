@extends('layouts.app')

@section('title', 'Vote recorded')

@section('content')
<div class="mx-auto max-w-lg text-center">
    <x-voter-progress current="done" />
    <div class="panel rounded-[28px] px-8 py-12" data-enter>
        <p class="kicker">Finished</p>
        <h1 class="display mt-4 text-5xl font-semibold text-[var(--ink)]">Your vote has been recorded</h1>
        <p class="lede mx-auto mt-4">Thank you. This Staff ID cannot vote again. Keep this receipt number for the Electoral Commission if needed. It does not show who you voted for.</p>
        <p class="mt-8 text-[11px] uppercase tracking-[0.18em] text-[var(--muted)]">Ballot receipt</p>
        <p class="mt-3 font-mono text-3xl tracking-[0.18em] text-[var(--forest)]" data-receipt>{{ $receipt }}</p>
    </div>
</div>
@endsection
