@extends('layouts.app')

@section('title', 'Ballot')

@section('content')
@php $offices = collect($ballot['positions']); @endphp
<div class="mx-auto max-w-3xl" x-data="ballotFlow({{ $offices->count() }})" x-cloak>
    <x-voter-progress current="ballot" />
    <p class="kicker" data-enter>Secret digital ballot</p>
    <div class="panel mt-4 rounded-[28px] p-8" data-enter>
        <p class="text-sm text-[var(--muted)]">{{ $voter->name }} · {{ $voter->staff_id }}</p>
        <p class="lede mt-3">One office at a time. Look at the photograph, choose, then continue. Your choices stay secret.</p>

        @error('ballot')
            <p class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('voter.ballot.store') }}" class="mt-6">
            @csrf
            <p class="kicker">Office <span x-text="step + 1"></span> of {{ $offices->count() }}</p>

            <div x-ref="panel">
                @foreach ($offices as $index => $position)
                    <fieldset data-step="{{ $index }}" x-show="step === {{ $index }}" x-cloak>
                        <legend class="display mt-3 text-5xl text-[var(--ink)]">{{ $position['position'] }}</legend>
                        <input type="hidden" name="selections[{{ $index }}][position_id]" value="{{ $position['id'] }}">

                        @if ($position['yes_no'])
                            @php $candidate = $position['candidates'][0]; @endphp
                            <input type="hidden" name="selections[{{ $index }}][candidate_id]" value="{{ $candidate['id'] }}">
                            <p class="mt-2 text-sm text-[var(--muted)]">This office is unopposed. Vote <strong>Yes</strong> to elect this candidate, or <strong>No</strong> if you do not support them.</p>
                            <div
                                class="ballot-yesno mt-5"
                                x-data="{ choice: '' }"
                                :data-choice="choice"
                            >
                                <div class="mx-auto max-w-sm overflow-hidden rounded-2xl bg-[#f3eee4] ring-1 ring-[color-mix(in_srgb,var(--gold)_22%,transparent)] transition"
                                     :class="choice === 'yes' ? 'ring-2 ring-[var(--gold)] shadow-[0_0_0_4px_color-mix(in_srgb,var(--gold)_25%,transparent)]' : ''">
                                    <div class="relative aspect-[3/4] w-full bg-[#e8e0d4]">
                                        @if ($candidate['photo_url'])
                                            <img src="{{ $candidate['photo_url'] }}" alt="{{ $candidate['name'] }}" class="absolute inset-0 h-full w-full object-cover object-top">
                                        @else
                                            <span class="absolute inset-0 flex items-center justify-center bg-[var(--forest)] text-5xl font-semibold text-[var(--ivory)]">{{ $candidate['initials'] }}</span>
                                        @endif
                                        <div class="ballot-mark pointer-events-none absolute inset-0 flex items-center justify-center bg-[color-mix(in_srgb,var(--forest)_42%,transparent)] text-[var(--ivory)]">
                                            <x-fingerprint class="h-28 w-28 drop-shadow-lg" />
                                        </div>
                                    </div>
                                    <p class="display px-4 py-4 text-center text-2xl font-semibold">{{ $candidate['name'] }}</p>
                                </div>
                                <div class="mx-auto mt-4 grid max-w-sm gap-3 sm:grid-cols-2">
                                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl bg-[#f3eee4] px-4 py-5 text-center ring-1 ring-[color-mix(in_srgb,var(--gold)_22%,transparent)] transition has-[:checked]:bg-[color-mix(in_srgb,var(--gold)_14%,#f3eee4)] has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)]">
                                        <input type="radio" name="selections[{{ $index }}][choice]" value="yes" required class="sr-only" x-model="choice">
                                        <x-fingerprint class="h-10 w-10 text-[var(--forest)] opacity-70" />
                                        <span class="display text-2xl font-semibold">Yes</span>
                                    </label>
                                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl bg-[#f3eee4] px-4 py-5 text-center ring-1 ring-[color-mix(in_srgb,var(--gold)_22%,transparent)] transition has-[:checked]:bg-[color-mix(in_srgb,#991b1b_10%,#f3eee4)] has-[:checked]:ring-2 has-[:checked]:ring-[#991b1b]">
                                        <input type="radio" name="selections[{{ $index }}][choice]" value="no" required class="sr-only" x-model="choice">
                                        <span class="display text-2xl font-semibold">No</span>
                                    </label>
                                </div>
                            </div>
                        @else
                            @if ($position['unopposed'])
                                <p class="mt-2 text-sm text-[var(--muted)]">This office is unopposed after vetting. Confirm the candidate to continue.</p>
                            @endif
                            <div class="mt-5 grid gap-4 {{ count($position['candidates']) === 1 ? 'mx-auto max-w-sm' : 'sm:grid-cols-2' }}">
                                @foreach ($position['candidates'] as $candidate)
                                    <label class="group flex cursor-pointer flex-col overflow-hidden rounded-2xl bg-[#f3eee4] ring-1 ring-[color-mix(in_srgb,var(--gold)_22%,transparent)] transition has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)] has-[:checked]:shadow-[0_0_0_4px_color-mix(in_srgb,var(--gold)_25%,transparent)]">
                                        <input type="radio" name="selections[{{ $index }}][candidate_id]" value="{{ $candidate['id'] }}" required class="sr-only" @checked($position['unopposed'])>
                                        <div class="relative aspect-[3/4] w-full bg-[#e8e0d4]">
                                            @if ($candidate['photo_url'])
                                                <img src="{{ $candidate['photo_url'] }}" alt="{{ $candidate['name'] }}" class="absolute inset-0 h-full w-full object-cover object-top">
                                            @else
                                                <span class="absolute inset-0 flex items-center justify-center bg-[var(--forest)] text-5xl font-semibold text-[var(--ivory)]">{{ $candidate['initials'] }}</span>
                                            @endif
                                            <div class="ballot-mark pointer-events-none absolute inset-0 flex items-center justify-center bg-[color-mix(in_srgb,var(--forest)_42%,transparent)] text-[var(--ivory)]">
                                                <x-fingerprint class="h-28 w-28 drop-shadow-lg" />
                                            </div>
                                        </div>
                                        <span class="display px-4 py-4 text-center text-2xl font-semibold">{{ $candidate['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                @endforeach
            </div>

            <p class="mt-4 text-sm text-red-700" x-show="needChoice" x-cloak>Make a choice before continuing.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <button type="button" class="btn btn-secondary" @click="prev()" :disabled="step === 0">Back</button>
                <button type="button" class="btn btn-primary flex-1" x-show="step < total - 1" @click="next()">Continue to next office</button>
                <button type="submit" class="btn btn-primary flex-1" x-show="step === total - 1" x-cloak>Cast ballot</button>
            </div>
        </form>
    </div>
</div>
@endsection
