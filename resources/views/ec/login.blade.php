@extends('layouts.app')

@section('title', 'EC login')

@section('content')
<div class="mx-auto max-w-md">
    <p class="kicker" data-enter>Restricted</p>
    <div class="panel mt-4 rounded-[28px] p-8" data-enter>
        <h1 class="display text-5xl font-semibold text-[var(--ink)]">Electoral Commission</h1>
        <p class="lede mt-4">Officials and the system administrator sign in here. Live counts stay private until official results are published.</p>
        <form method="POST" action="{{ route('ec.login.attempt') }}" class="mt-8 space-y-5">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field mt-2">
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required class="field mt-2">
                @error('email')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="btn btn-primary w-full">Sign in</button>
        </form>
    </div>
</div>
@endsection
