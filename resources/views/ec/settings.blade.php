@extends('layouts.app')

@section('title', 'Election settings')

@section('content')
@php
    $unopposedOn = old('unopposed_voting_enabled', $election->unopposed_voting_enabled);
    $scope = old('unopposed_voting_scope', $election->unopposed_voting_scope);
    $failOutcome = old('unopposed_fail_outcome', $election->unopposed_fail_outcome);
    $locked = $election->ballotLocked() || $election->isClosed();
@endphp
<p class="kicker">Schedule</p>
<h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Election settings</h1>
<p class="lede mt-3">Set the name and times. Open voting only when the ballot and voter register are ready.</p>

<form method="POST" action="{{ route('ec.settings.update') }}" class="panel mt-8 max-w-2xl space-y-5 rounded-[28px] p-8" x-data="{
    enabled: {{ $unopposedOn ? 'true' : 'false' }},
    scope: @js($scope ?: 'global'),
    fail: @js($failOutcome ?: 'open_nominations'),
}">
    @csrf
    @method('PUT')
    <div>
        <label class="label" for="name">Election name</label>
        <input id="name" name="name" value="{{ old('name', $election->name) }}" required class="field mt-2" @disabled($election->isClosed())>
        @error('name')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="label" for="opens_at">Opens</label>
        <input id="opens_at" name="opens_at" type="datetime-local" value="{{ old('opens_at', $election->opens_at->timezone(config('app.timezone'))->format('Y-m-d\\TH:i')) }}" required class="field mt-2" @disabled($election->isClosed())>
    </div>
    <div>
        <label class="label" for="closes_at">Closes</label>
        <input id="closes_at" name="closes_at" type="datetime-local" value="{{ old('closes_at', $election->closes_at->timezone(config('app.timezone'))->format('Y-m-d\\TH:i')) }}" required class="field mt-2" @disabled($election->isClosed())>
    </div>

    <div class="border-t border-[color-mix(in_srgb,var(--gold)_20%,transparent)] pt-5">
        <p class="kicker">Unopposed offices</p>
        <h2 class="display mt-2 text-3xl text-[var(--ink)]">Yes / No voting</h2>
        <p class="mt-2 text-sm text-[var(--muted)]">When a position has only one candidate, voters can vote Yes or No instead of being locked to that person.</p>

        <label class="mt-4 flex items-start gap-3">
            <input type="checkbox" name="unopposed_voting_enabled" value="1" class="mt-1" x-model="enabled" @checked($unopposedOn) @disabled($locked)>
            <span>
                <span class="font-medium">Enable unopposed Yes / No</span>
                <span class="mt-1 block text-sm text-[var(--muted)]">Turn this off to keep the previous confirm-only behaviour.</span>
            </span>
        </label>

        <div class="mt-4 space-y-4" x-show="enabled" x-cloak>
            <div>
                <p class="label">Apply settings</p>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="unopposed_voting_scope" value="global" x-model="scope" @disabled($locked)>
                        Global for every 1-candidate office
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="unopposed_voting_scope" value="per_position" x-model="scope" @disabled($locked)>
                        Per position (configure on Ballot setup)
                    </label>
                </div>
            </div>

            <div class="space-y-4 rounded-2xl bg-[#f3eee4] p-4" x-show="scope === 'global'" x-cloak>
                <div>
                    <p class="label">Yes threshold type</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="unopposed_threshold_type" value="percent" @checked(old('unopposed_threshold_type', $election->unopposed_threshold_type) === 'percent') @disabled($locked)>
                            Percent of Yes + No votes
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="unopposed_threshold_type" value="count" @checked(old('unopposed_threshold_type', $election->unopposed_threshold_type) === 'count') @disabled($locked)>
                            Minimum Yes count
                        </label>
                    </div>
                </div>
                <div>
                    <label class="label" for="unopposed_threshold_value">Threshold value</label>
                    <input id="unopposed_threshold_value" name="unopposed_threshold_value" type="number" min="1" max="100000" value="{{ old('unopposed_threshold_value', $election->unopposed_threshold_value) }}" class="field mt-2" @disabled($locked)>
                    <p class="mt-1 text-xs text-[var(--muted)]">For percent, use 1–100 (e.g. 50). For count, use the minimum Yes votes required.</p>
                </div>
                <div>
                    <p class="label">If Yes threshold is not met</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="unopposed_fail_outcome" value="open_nominations" x-model="fail" @disabled($locked)>
                            Open for nominations
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="unopposed_fail_outcome" value="other" x-model="fail" @disabled($locked)>
                            Other (custom note)
                        </label>
                    </div>
                </div>
                <div x-show="fail === 'other'" x-cloak>
                    <label class="label" for="unopposed_fail_note">Custom note</label>
                    <textarea id="unopposed_fail_note" name="unopposed_fail_note" rows="3" class="field mt-2" @disabled($locked)>{{ old('unopposed_fail_note', $election->unopposed_fail_note) }}</textarea>
                </div>
            </div>

            <p class="text-sm text-[var(--muted)]" x-show="scope === 'per_position'" x-cloak>
                After saving, open Ballot setup. Each office with exactly one candidate can turn on Yes / No and set its own threshold and outcome.
            </p>
        </div>
    </div>

    @unless($election->isClosed())
        <button class="btn btn-primary">Save settings</button>
    @endunless
</form>
@endsection
