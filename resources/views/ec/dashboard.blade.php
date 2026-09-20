@extends('layouts.app')

@section('title', $election?->isPublished() ? 'Official results' : ($election?->isClosed() ? 'Closed counts' : 'Live results'))

@section('content')
<div class="mb-10 flex flex-wrap items-start justify-between gap-6">
    <div>
        <p class="kicker">
            @if ($election?->isPublished())
                Published
            @elseif ($election?->isOpen())
                Voting open
            @elseif ($election?->isClosed())
                Closed — paper count pending
            @else
                Setup
            @endif
            @if (auth()->user()?->isAdmin())
                · Administrator
            @endif
        </p>
        <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">
            {{ $election?->isPublished() ? 'Official results' : ($election?->isClosed() ? 'Closed counts' : 'Live results') }}
        </h1>
        <p class="mt-3 text-[var(--muted)]">{{ $election?->name ?? 'No election' }}</p>
        <p class="mt-1 text-sm text-[var(--muted)]">Turnout {{ $votedCount }} / {{ $voterCount }} · Digital {{ $digitalCount }} · Paper {{ $paperCount }}</p>
        @if ($election)
            <p class="text-sm text-[var(--muted)]">{{ $election->opens_at->timezone(config('app.timezone'))->format('j M Y H:i') }} – {{ $election->closes_at->timezone(config('app.timezone'))->format('j M Y H:i') }}</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2 no-print">
        @if ($election && ! $election->isClosed() && $election->status !== 'open')
            <form method="POST" action="{{ route('ec.open') }}">@csrf<button class="btn btn-primary">Open voting</button></form>
        @endif
        @if ($election && $election->status === 'open' && ! $election->hasVotes())
            <form method="POST" action="{{ route('ec.setup') }}">@csrf<button class="btn btn-secondary">Pause for setup</button></form>
        @endif
        @if ($election && $election->status === 'open')
            <form method="POST" action="{{ route('ec.close') }}" onsubmit="return confirm('Close the election now? Voting will stop.')">@csrf<button class="btn btn-danger">Close election</button></form>
        @endif
        @if (auth()->user()?->isAdmin() && $election && ($election->isClosed() || $election->isPublished()))
            <form method="POST" action="{{ route('ec.restore') }}" onsubmit="return confirm('Restore the voting period? Official results will leave the public page, and staff who have not voted can vote again.')">@csrf<button class="btn btn-gold">Restore voting period</button></form>
        @endif
        @if ($election?->isClosed() && ! $election->isPublished())
            <a href="{{ route('ec.tally') }}" class="btn btn-primary">Enter paper counts</a>
        @endif
        <a href="{{ route('ec.ballot.print') }}" class="btn btn-secondary">Print paper ballot</a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print this page</button>
    </div>
</div>

<div id="results" class="grid gap-5 md:grid-cols-2">
    @foreach ($results['positions'] as $position)
        <section class="panel rounded-[28px] p-6">
            <h2 class="display text-3xl text-[var(--ink)]">{{ $position['position'] }}</h2>
            @php
                $leaders = collect($position['leaders'] ?? []);
                $unopposed = $position['unopposed'] ?? null;
            @endphp
            @if (! empty($position['yes_no']) && $unopposed)
                <p class="mt-2 text-sm text-[var(--muted)]">Yes {{ $unopposed['yes'] }} · No {{ $unopposed['no'] }}
                    @if ($unopposed['threshold_type'] === 'percent')
                        · Need {{ $unopposed['threshold_value'] }}% Yes
                    @else
                        · Need {{ $unopposed['threshold_value'] }} Yes
                    @endif
                </p>
                @if ($unopposed['threshold_met'])
                    <p class="mt-1 text-sm text-[var(--forest)]">Leading: {{ $leaders->join(', ') }}</p>
                @elseif ($unopposed['message'])
                    <p class="mt-1 text-sm text-[var(--forest)]">{{ $unopposed['message'] }}</p>
                @endif
            @elseif ($leaders->isNotEmpty() && $position['leading_votes'] > 0)
                <p class="mt-2 text-sm text-[var(--forest)]">
                    {{ $position['tied'] ? 'Tie: ' : 'Leading: ' }}{{ $leaders->join(', ') }}
                </p>
            @endif
            <ul class="mt-5 space-y-3">
                @foreach ($position['candidates'] as $candidate)
                    <li class="flex items-center justify-between gap-3 rounded-2xl bg-[#f3eee4] px-3 py-3 {{ $leaders->contains($candidate['name']) && $position['leading_votes'] > 0 ? 'ring-1 ring-[var(--gold)]' : '' }}">
                        <div class="flex items-center gap-3">
                            <x-portrait :name="$candidate['name']" :photo="$candidate['photo_url'] ?? null" :initials="$candidate['initials'] ?? null" size="sm" />
                            <div>
                                <p class="font-medium">{{ $candidate['name'] }}</p>
                                @if ($election?->isClosed())
                                    @if (! empty($position['yes_no']))
                                        <p class="text-xs text-[var(--muted)]">Digital Yes {{ $candidate['digital_votes'] }} · Paper Yes {{ $candidate['manual_votes'] }} · No {{ $candidate['no_votes'] }}</p>
                                    @else
                                        <p class="text-xs text-[var(--muted)]">Digital {{ $candidate['digital_votes'] }} · Paper {{ $candidate['manual_votes'] }}</p>
                                    @endif
                                @elseif (! empty($position['yes_no']))
                                    <p class="text-xs text-[var(--muted)]">Yes {{ $candidate['votes'] }} · No {{ $candidate['no_votes'] }}</p>
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

@if ($election?->isOpen())
<script>
    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[char]));
    }
    async function poll() {
        const response = await fetch(@json(url('/api/v1/admin/results/live')), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        const root = document.getElementById('results');
        root.innerHTML = data.positions.map((position) => {
            const leaders = position.leaders || [];
            const unopposed = position.unopposed || null;
            let statusLine = '';
            if (position.yes_no && unopposed) {
                const need = unopposed.threshold_type === 'percent'
                    ? `Need ${unopposed.threshold_value}% Yes`
                    : `Need ${unopposed.threshold_value} Yes`;
                statusLine = `<p class="mt-2 text-sm text-[var(--muted)]">Yes ${unopposed.yes} · No ${unopposed.no} · ${need}</p>`;
                if (unopposed.threshold_met) {
                    statusLine += `<p class="mt-1 text-sm text-[var(--forest)]">Leading: ${leaders.map(escapeHtml).join(', ')}</p>`;
                } else if (unopposed.message) {
                    statusLine += `<p class="mt-1 text-sm text-[var(--forest)]">${escapeHtml(unopposed.message)}</p>`;
                }
            } else if (position.leading_votes > 0) {
                statusLine = `<p class="mt-2 text-sm text-[var(--forest)]">${position.tied ? 'Tie: ' : 'Leading: '}${leaders.map(escapeHtml).join(', ')}</p>`;
            }
            return `
            <section class="panel rounded-[28px] p-6">
                <h2 class="display text-3xl text-[var(--ink)]">${escapeHtml(position.position)}</h2>
                ${statusLine}
                <ul class="mt-5 space-y-3">
                    ${position.candidates.map((candidate) => {
                        const leading = position.leading_votes > 0 && leaders.includes(candidate.name);
                        const photo = candidate.photo_url
                            ? `<img src="${escapeHtml(candidate.photo_url)}" alt="" class="h-10 w-10 rounded-full object-cover portrait-ring">`
                            : `<span class="portrait-ring inline-flex h-10 w-10 items-center justify-center rounded-full bg-[var(--forest)] text-[11px] text-[var(--ivory)]">${escapeHtml(candidate.initials || '')}</span>`;
                        const detail = position.yes_no
                            ? `<p class="text-xs text-[var(--muted)]">Yes ${candidate.votes} · No ${candidate.no_votes || 0}</p>`
                            : '';
                        return `
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-[#f3eee4] px-3 py-3${leading ? ' ring-1 ring-[var(--gold)]' : ''}">
                            <div class="flex items-center gap-3">${photo}<div><p class="font-medium">${escapeHtml(candidate.name)}</p>${detail}</div></div>
                            <strong class="display text-3xl">${candidate.votes}</strong>
                        </li>`;
                    }).join('')}
                </ul>
            </section>`;
        }).join('');
    }
    setInterval(poll, 5000);
</script>
@endif
@endsection
