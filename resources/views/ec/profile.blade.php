@extends('layouts.app')

@section('title', 'Account')

@section('content')
<div class="mb-8">
    <p class="kicker">Your account</p>
    <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Profile</h1>
    <p class="mt-2 max-w-2xl text-sm text-[#5c5346]">Change the password for this Electoral Commission or administrator account. After handover, set a new password so only your team can sign in.</p>
</div>

<div class="panel max-w-xl rounded-[28px] p-8">
    <dl class="space-y-3 text-sm">
        <div>
            <dt class="text-[var(--muted)]">Name</dt>
            <dd class="mt-1 font-medium text-[var(--ink)]">{{ $user->name }}</dd>
        </div>
        <div>
            <dt class="text-[var(--muted)]">Email</dt>
            <dd class="mt-1 font-medium text-[var(--ink)]">{{ $user->email }}</dd>
        </div>
        <div>
            <dt class="text-[var(--muted)]">Role</dt>
            <dd class="mt-1 font-medium uppercase tracking-[0.14em] text-[var(--ink)]">{{ $user->role }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('ec.profile.password') }}" class="mt-8 space-y-5 border-t border-[color-mix(in_srgb,var(--gold)_25%,transparent)] pt-8">
        @csrf
        @method('PUT')

        <div>
            <label class="label" for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="field mt-2">
            @error('current_password')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="field mt-2">
            @error('password')
                <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field mt-2">
        </div>

        <button class="btn btn-primary">Update password</button>
    </form>
</div>
@endsection
