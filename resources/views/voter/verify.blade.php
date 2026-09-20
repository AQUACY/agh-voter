@extends('layouts.app')

@section('title', 'Enter SMS code')

@section('content')
<div class="mx-auto max-w-lg">
    <x-voter-progress current="code" />
    <p class="kicker" data-enter>Confirm it is you</p>
    <div class="panel mt-4 rounded-[28px] p-8" data-enter>
        <h1 class="display text-5xl font-semibold text-[var(--ink)]">Enter the SMS code</h1>
        <div class="mt-6 rounded-2xl bg-[#f3eee4] px-5 py-4">
            <p class="kicker">This code is for</p>
            <p class="display mt-2 text-4xl text-[var(--ink)]">{{ $staffName ?: $staffId }}</p>
            <p class="mt-2 text-sm text-[var(--muted)]">Staff ID <strong class="tracking-wide">{{ $staffId }}</strong></p>
            <p class="text-sm text-[var(--muted)]">Registered phone <strong>{{ $phoneMasked }}</strong></p>
        </div>
        <p class="lede mt-4">If this is not you, go back and check the Staff ID. The code expires in 5 minutes. Do not share it.</p>

        <form method="POST" action="{{ route('voter.otp.verify') }}" class="mt-7 space-y-5">
            @csrf
            <input type="hidden" name="staff_id" value="{{ $staffId }}">
            <div>
                <label for="otp" class="label">6-digit code</label>
                <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus class="field mt-2 text-center text-3xl tracking-[0.45em]">
                @error('otp')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @error('staff_id')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="btn btn-primary w-full">Continue</button>
        </form>

        <form method="POST" action="{{ route('voter.otp.resend') }}" class="mt-4">
            @csrf
            <input type="hidden" name="staff_id" value="{{ $staffId }}">
            <button class="w-full min-h-11 text-sm text-[var(--forest)] underline-offset-4 hover:underline">Resend SMS code</button>
        </form>
    </div>
</div>
@endsection
