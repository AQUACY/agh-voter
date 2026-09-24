@extends('layouts.app')

@section('title', 'Ballot setup')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="kicker">Ballot</p>
        <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Positions and portraits</h1>
        <p class="mt-2 max-w-xl text-sm text-[#5c5346]">
            @if ($election->ballotLocked())
                Names are locked after voting starts. Portraits can still be updated for the paper ballot and results.
            @else
                Add positions and at least two candidates for each before opening. Upload a clear passport-style photograph — it prints large on the A4 ballot.
            @endif
        </p>
    </div>
    <a href="{{ route('ec.ballot.print') }}" class="btn btn-primary">Print A4 paper ballot</a>
</div>

@unless($election->ballotLocked())
    <form method="POST" action="{{ route('ec.positions.store') }}" class="panel mb-6 flex flex-wrap gap-3 rounded-[24px] border border-[#c4a35a]/15 p-4">
        @csrf
        <input name="name" required placeholder="Position name" class="flex-1 rounded-2xl bg-[#f4efe6] px-3 py-2 ring-1 ring-[#c4a35a]/20">
        <button class="rounded-full bg-[#12352c] px-4 py-2 text-sm text-[#f4efe6]">Add position</button>
        @error('name')<p class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
    </form>
@endunless

<div class="space-y-5">
    @forelse ($election->positions as $position)
        <section class="panel rounded-[28px] border border-[#c4a35a]/15 p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="display text-2xl text-[#08140f]">{{ $position->name }}</h2>
                @unless($election->ballotLocked())
                    <form method="POST" action="{{ route('ec.positions.destroy', $position) }}" onsubmit="return confirm('Remove this position and its candidates?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm text-red-800">Remove</button>
                    </form>
                @endunless
            </div>

            @if ($election->unopposed_voting_enabled && $election->unopposed_voting_scope === 'per_position' && $position->candidates->count() === 1)
                @php
                    $fail = old('unopposed_fail_outcome', $position->unopposed_fail_outcome) ?: 'open_nominations';
                @endphp
                <form method="POST" action="{{ route('ec.positions.unopposed', $position) }}" class="mt-4 space-y-3 rounded-2xl bg-[#f4efe6] p-4" x-data="{
                    enabled: {{ old('unopposed_yes_no', $position->unopposed_yes_no) ? 'true' : 'false' }},
                    fail: @js($fail),
                }">
                    @csrf
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="unopposed_yes_no" value="1" class="mt-1" x-model="enabled" @checked(old('unopposed_yes_no', $position->unopposed_yes_no)) @disabled($election->ballotLocked())>
                        <span>
                            <span class="font-medium">Yes / No for this unopposed office</span>
                            <span class="mt-1 block text-xs text-[#6b6254]">Voters choose Yes or No instead of a locked confirmation.</span>
                        </span>
                    </label>
                    <div class="space-y-3" x-show="enabled" x-cloak>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="unopposed_threshold_type" value="percent" @checked(old('unopposed_threshold_type', $position->unopposed_threshold_type) === 'percent') @disabled($election->ballotLocked())>
                                Percent
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="unopposed_threshold_type" value="count" @checked(old('unopposed_threshold_type', $position->unopposed_threshold_type) === 'count') @disabled($election->ballotLocked())>
                                Minimum Yes count
                            </label>
                        </div>
                        <input name="unopposed_threshold_value" type="number" min="1" max="100000" value="{{ old('unopposed_threshold_value', $position->unopposed_threshold_value) }}" placeholder="Threshold value" class="w-full rounded-2xl bg-[#fffaf2] px-3 py-2 ring-1 ring-[#c4a35a]/20" @disabled($election->ballotLocked())>
                        <p class="text-xs text-[#6b6254]">Percent needs more than that share of Yes + No. At 50%, that is half of turnout plus one Yes.</p>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="unopposed_fail_outcome" value="open_nominations" x-model="fail" @disabled($election->ballotLocked())>
                                Open for nominations
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="unopposed_fail_outcome" value="other" x-model="fail" @disabled($election->ballotLocked())>
                                Other
                            </label>
                        </div>
                        <textarea name="unopposed_fail_note" rows="2" placeholder="Custom note if Other" class="w-full rounded-2xl bg-[#fffaf2] px-3 py-2 ring-1 ring-[#c4a35a]/20" x-show="fail === 'other'" x-cloak @disabled($election->ballotLocked())>{{ old('unopposed_fail_note', $position->unopposed_fail_note) }}</textarea>
                        @unless($election->ballotLocked())
                            <button class="rounded-full bg-[#12352c] px-3 py-2 text-sm text-[#f4efe6]">Save Yes/No settings</button>
                        @endunless
                    </div>
                </form>
            @elseif ($election->unopposed_voting_enabled && $election->unopposed_voting_scope === 'global' && $position->candidates->count() === 1)
                <p class="mt-3 text-sm text-[#5c5346]">Using global Yes / No settings from Election settings.</p>
            @endif

            <ul class="mt-4 space-y-3">
                @foreach ($position->candidates as $candidate)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#f4efe6] px-4 py-3">
                        <div class="flex items-center gap-4">
                            @if ($candidate->photoUrl())
                                <img src="{{ $candidate->photoUrl() }}" alt="{{ $candidate->name }}" class="h-24 w-20 object-cover object-top ring-1 ring-[#c4a35a]/50">
                            @else
                                <span class="inline-flex h-24 w-20 items-center justify-center bg-[#12352c] text-lg font-semibold text-[#f4efe6]">{{ $candidate->initials() }}</span>
                            @endif
                            <span class="display text-2xl">{{ $candidate->name }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('ec.candidates.photo', $candidate) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="max-w-48 text-xs">
                                <button class="text-sm text-[#12352c]">Save portrait</button>
                            </form>
                            @unless($election->ballotLocked())
                                <form method="POST" action="{{ route('ec.candidates.destroy', $candidate) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm text-red-800">Remove</button>
                                </form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
            @unless($election->ballotLocked())
                <form method="POST" action="{{ route('ec.candidates.store', $position) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input name="name" required placeholder="Candidate name" class="flex-1 rounded-2xl bg-[#fffaf2] px-3 py-2 ring-1 ring-[#c4a35a]/20">
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="text-xs">
                    <button class="rounded-full bg-[#12352c] px-3 py-2 text-sm text-[#f4efe6]">Add</button>
                </form>
            @endunless
        </section>
    @empty
        <p class="text-[#6b6254]">No positions yet.</p>
    @endforelse
</div>
@endsection
