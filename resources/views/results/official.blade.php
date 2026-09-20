@extends('layouts.app')

@section('title', $results ? 'Official results' : 'Results pending')

@section('content')
<div class="mb-10">
    <p class="kicker">Asesewa Government Hospital</p>
    <h1 class="display mt-3 text-5xl font-semibold text-[var(--ink)]">{{ $results ? 'Official results' : 'Official results pending' }}</h1>
    <p class="mt-3 text-[var(--muted)]">{{ $election?->name ?? 'Welfare Election' }}</p>
</div>

@if (! $results)
    <div class="panel max-w-xl rounded-[28px] p-8">
        <p class="lede">The Electoral Commission will publish official results after voting closes and paper ballots have been counted. Live counts are not public during voting.</p>
    </div>
@else
    <p class="mb-6 text-sm text-[var(--muted)]">
        Turnout {{ $results['turnout']['voted'] }} / {{ $results['turnout']['registered'] }}
        · Digital {{ $results['turnout']['digital'] }}
        · Paper {{ $results['turnout']['paper'] }}
    </p>
    <div class="grid gap-5 md:grid-cols-2">
        @foreach ($results['positions'] as $position)
            <section class="panel rounded-[28px] p-6">
                <h2 class="display text-3xl text-[var(--ink)]">{{ $position['position'] }}</h2>
                @php
                    $leaders = collect($position['leaders'] ?? []);
                    $unopposed = $position['unopposed'] ?? null;
                @endphp
                @if (! empty($position['yes_no']) && $unopposed)
                    <p class="mt-2 text-sm text-[var(--muted)]">Yes {{ $unopposed['yes'] }} · No {{ $unopposed['no'] }}</p>
                    @if ($unopposed['threshold_met'])
                        <p class="mt-1 text-sm text-[var(--forest)]">Elected: {{ $leaders->join(', ') }}</p>
                    @elseif ($unopposed['message'])
                        <p class="mt-1 text-sm text-[var(--forest)]">{{ $unopposed['message'] }}</p>
                    @endif
                @elseif ($leaders->isNotEmpty() && $position['leading_votes'] > 0)
                    <p class="mt-2 text-sm text-[var(--forest)]">{{ $position['tied'] ? 'Tie: ' : 'Elected: ' }}{{ $leaders->join(', ') }}</p>
                @endif
                <ul class="mt-5 space-y-3">
                    @foreach ($position['candidates'] as $candidate)
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-[#f3eee4] px-3 py-3 {{ $leaders->contains($candidate['name']) && $position['leading_votes'] > 0 ? 'ring-1 ring-[var(--gold)]' : '' }}">
                            <div class="flex items-center gap-3">
                                <x-portrait :name="$candidate['name']" :photo="$candidate['photo_url'] ?? null" :initials="$candidate['initials'] ?? null" size="sm" />
                                <div>
                                    <p class="font-medium">{{ $candidate['name'] }}</p>
                                    @if (! empty($position['yes_no']))
                                        <p class="text-xs text-[var(--muted)]">Yes {{ $candidate['votes'] }} · No {{ $candidate['no_votes'] }} · Digital Yes {{ $candidate['digital_votes'] }} · Paper Yes {{ $candidate['manual_votes'] }}</p>
                                    @else
                                        <p class="text-xs text-[var(--muted)]">Digital {{ $candidate['digital_votes'] }} · Paper {{ $candidate['manual_votes'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <strong class="display text-3xl">{{ $candidate['votes'] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
@endsection
