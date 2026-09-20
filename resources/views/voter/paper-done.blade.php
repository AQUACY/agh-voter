@extends('layouts.app')

@section('title', 'Paper ballot issued')

@section('content')
<div class="mx-auto max-w-lg text-center">
    <x-voter-progress current="done" />
    <div class="panel rounded-[28px] px-8 py-12" data-enter>
        <p class="kicker">Paper issued</p>
        <h1 class="display mt-4 text-5xl font-semibold text-[var(--ink)]">Digital voting is closed for this Staff ID</h1>
        <p class="lede mx-auto mt-4">{{ $staffName }} ({{ $staffId }}) has the official sheets. Thumb-mark them, fold them, and drop them in the ballot box. This person cannot vote online.</p>
        <a href="{{ route('voter.enter') }}" class="btn btn-primary mt-8">Next voter</a>
    </div>
</div>
@endsection
