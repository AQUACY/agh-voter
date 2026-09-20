@extends('layouts.app')

@section('title', 'Paper count')

@section('content')
<div class="mb-8">
    <p class="kicker">End of day</p>
    <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Paper ballot count</h1>
    <p class="lede mt-4">
        After voting closes, count the A4 paper ballots and enter those totals here. Digital votes are already stored.
        Official results stay private until you publish.
    </p>
</div>

@if (! $election->isClosed())
    <p class="rounded-2xl bg-[#f4efe6] px-4 py-3 text-sm text-[#5c5346]">Close the election before entering paper counts.</p>
@elseif ($election->isPublished())
    <p class="rounded-2xl bg-[#f4efe6] px-4 py-3 text-sm text-[#5c5346]">Results are published. Counts can no longer change.</p>
@else
    <form method="POST" action="{{ route('ec.tally.save') }}" class="space-y-5">
        @csrf
        @method('PUT')
        @foreach ($election->positions as $position)
            @php
                $row = collect($results['positions'])->firstWhere('position', $position->name);
                $yesNo = (bool) ($row['yes_no'] ?? false);
            @endphp
            <section class="panel rounded-[28px] border border-[#c4a35a]/15 p-6">
                <h2 class="display text-2xl text-[#08140f]">{{ $position->name }}</h2>
                @if ($yesNo)
                    <p class="mt-1 text-sm text-[#6b6254]">Unopposed Yes / No office — enter paper Yes and No marks separately.</p>
                @endif
                <div class="mt-4 space-y-3">
                    @foreach ($position->candidates as $candidate)
                        @php
                            $live = collect($row['candidates'] ?? [])->firstWhere('id', $candidate->id);
                        @endphp
                        @if ($yesNo)
                            <div class="rounded-2xl bg-[#f4efe6] px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-portrait :name="$candidate->name" :photo="$candidate->photoUrl()" :initials="$candidate->initials()" size="sm" />
                                    <div>
                                        <p class="font-medium">{{ $candidate->name }}</p>
                                        <p class="text-xs text-[#6b6254]">Digital Yes {{ $live['digital_votes'] ?? 0 }} · Digital No {{ $live['digital_no_votes'] ?? 0 }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-xs uppercase tracking-wide text-[#6b6254]">Paper Yes</span>
                                        <input type="number" min="0" name="counts[{{ $candidate->id }}]" value="{{ old('counts.'.$candidate->id, $tallies[$candidate->id] ?? 0) }}" class="mt-1 w-full rounded-2xl bg-[#fffaf2] px-3 py-2 text-right ring-1 ring-[#c4a35a]/30">
                                    </label>
                                    <label class="block">
                                        <span class="text-xs uppercase tracking-wide text-[#6b6254]">Paper No</span>
                                        <input type="number" min="0" name="no_counts[{{ $candidate->id }}]" value="{{ old('no_counts.'.$candidate->id, $noTallies[$candidate->id] ?? 0) }}" class="mt-1 w-full rounded-2xl bg-[#fffaf2] px-3 py-2 text-right ring-1 ring-[#c4a35a]/30">
                                    </label>
                                </div>
                            </div>
                        @else
                            <label class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#f4efe6] px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-portrait :name="$candidate->name" :photo="$candidate->photoUrl()" :initials="$candidate->initials()" size="sm" />
                                    <div>
                                        <p class="font-medium">{{ $candidate->name }}</p>
                                        <p class="text-xs text-[#6b6254]">Digital already counted: {{ $live['digital_votes'] ?? 0 }}</p>
                                    </div>
                                </div>
                                <input type="number" min="0" name="counts[{{ $candidate->id }}]" value="{{ old('counts.'.$candidate->id, $tallies[$candidate->id] ?? 0) }}" class="w-28 rounded-2xl bg-[#fffaf2] px-3 py-2 text-right ring-1 ring-[#c4a35a]/30">
                            </label>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary">Save paper counts</button>
        </div>
    </form>

    <form method="POST" action="{{ route('ec.publish') }}" class="mt-8" onsubmit="return confirm('Publish official results for everyone to see? An administrator can restore voting if this was a mistake.')">
        @csrf
        <button class="btn btn-gold">Publish official results</button>
    </form>
@endif
@endsection
