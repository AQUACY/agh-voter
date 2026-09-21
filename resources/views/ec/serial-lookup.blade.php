@extends('layouts.app')

@section('title', 'Ballot serial lookup')

@section('content')
<div class="mb-8">
    <p class="kicker">Paper ballots</p>
    <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Serial lookup</h1>
    <p class="mt-2 max-w-2xl text-sm text-[#5c5346]">Scan or type the ballot serial from a paper sheet. Only Electoral Commission officers can resolve a serial to a Staff ID. The printed ballot never shows the Staff ID.</p>
</div>

<form method="GET" action="{{ route('ec.serials') }}" class="panel mb-6 flex flex-wrap gap-3 rounded-[24px] border border-[#c4a35a]/15 p-5">
    <input name="serial" value="{{ $serial }}" required autofocus placeholder="e.g. ABCD-EFGH-IJKL" class="min-w-[16rem] flex-1 rounded-2xl bg-[#f4efe6] px-3 py-2 font-mono tracking-[0.12em] ring-1 ring-[#c4a35a]/20">
    <button class="rounded-full bg-[#12352c] px-5 py-2 text-[#f4efe6]">Look up</button>
</form>

@if ($searched)
    @if ($match)
        <div class="panel rounded-[28px] border border-[#c4a35a]/15 p-8">
            <p class="kicker">Matched voter</p>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Ballot serial</dt>
                    <dd class="mt-1 font-mono text-lg tracking-[0.12em]">{{ $match->formatted() }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Issued</dt>
                    <dd class="mt-1">{{ $match->issued_at?->timezone(config('app.timezone'))->format('d M Y · H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Staff ID</dt>
                    <dd class="mt-1 font-mono text-lg">{{ $match->voter->staff_id }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Name</dt>
                    <dd class="mt-1 text-lg">{{ $match->voter->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Phone</dt>
                    <dd class="mt-1">{{ $match->voter->maskedPhone() }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">Ballot status</dt>
                    <dd class="mt-1">{{ $match->voter->voteChannelLabel() }}</dd>
                </div>
            </dl>
        </div>
    @else
        <div class="panel rounded-[24px] border border-red-200 bg-red-50/60 p-6 text-sm text-red-900">
            No paper ballot serial matches <span class="font-mono">{{ $serial }}</span> for this election.
        </div>
    @endif
@endif
@endsection
