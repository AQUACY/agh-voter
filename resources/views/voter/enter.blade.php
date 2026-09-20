@extends('layouts.app')

@section('title', 'Staff verification')

@section('content')
<div class="mx-auto max-w-lg">
    <x-voter-progress current="staff" />
    <p class="kicker" data-enter>Staff verification</p>
    <div class="panel mt-4 rounded-[28px] p-8" data-enter>
        <h1 class="display text-5xl font-semibold text-[var(--ink)]">Enter your Staff ID</h1>
        @if (! $election?->isOpen())
            <p class="mt-5 rounded-2xl bg-[#f3eee4] px-4 py-3 text-sm text-[var(--muted)]">
                @if ($election?->isClosed())
                    Voting is closed.
                @else
                    Voting is not open yet. Please wait for the Electoral Commission.
                @endif
            </p>
        @else
            <p class="lede mt-4">We will send a 6-digit SMS code to the phone registered for this Staff ID. After the code, choose paper or online at the Electoral Commission table. You do not need WhatsApp, email, or an authenticator app.</p>
        @endif

        <form method="POST" action="{{ route('voter.otp.request') }}" class="mt-8 space-y-5">
            @csrf
            <div>
                <label for="staff_id" class="label">Staff ID</label>
                <input id="staff_id" name="staff_id" value="{{ old('staff_id') }}" required autofocus class="field mt-2 tracking-[0.18em]" placeholder="AGH00123" @disabled(! $election?->isOpen())>
                @error('staff_id')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="btn btn-primary w-full" @disabled(! $election?->isOpen())>Continue to SMS code</button>
        </form>
    </div>
</div>
@endsection
