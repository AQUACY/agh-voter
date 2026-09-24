@extends('layouts.app')

@section('title', 'Voters')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-3">
    <div>
        <p class="kicker">Register</p>
        <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Registered voters</h1>
        <p class="mt-2 max-w-2xl text-sm text-[#5c5346]">Voters cast online after Staff ID and SMS. Use Paper vote when the EC issues a printed sheet — that prints a QR serial (not the Staff ID) and closes online voting for that Staff ID. Use Reprint if the printer failed, power cut mid-print, or a sheet was spoiled; the same serial is printed again. Resolve a serial under Serial lookup.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a class="text-sm text-[#12352c] underline-offset-4 hover:underline" href="{{ route('ec.serials') }}">Serial lookup</a>
        <a class="text-sm text-[#12352c] underline-offset-4 hover:underline" href="{{ route('ec.voters.template') }}">Download CSV template</a>
    </div>
</div>

<form method="GET" action="{{ route('ec.voters') }}" class="mb-5 flex gap-2">
    <input name="q" value="{{ $q }}" placeholder="Search Staff ID, name, or ballot serial" class="flex-1 rounded-2xl bg-[#fffaf2] px-3 py-2 ring-1 ring-[#c4a35a]/20">
    <button class="rounded-full bg-white px-4 py-2 ring-1 ring-[#c4a35a]/30">Search</button>
</form>

@unless($election?->registerLocked())
    <form method="POST" action="{{ route('ec.voters.store') }}" class="panel mb-5 grid gap-3 rounded-[24px] border border-[#c4a35a]/15 p-5 md:grid-cols-4">
        @csrf
        <input name="staff_id" value="{{ old('staff_id') }}" required placeholder="Staff ID" class="rounded-2xl bg-[#f4efe6] px-3 py-2 ring-1 ring-[#c4a35a]/20">
        <input name="name" value="{{ old('name') }}" required placeholder="Full name" class="rounded-2xl bg-[#f4efe6] px-3 py-2 ring-1 ring-[#c4a35a]/20">
        <input name="phone" value="{{ old('phone') }}" required placeholder="024XXXXXXX" class="rounded-2xl bg-[#f4efe6] px-3 py-2 ring-1 ring-[#c4a35a]/20">
        <button class="rounded-full bg-[#12352c] px-4 py-2 text-[#f4efe6]">Add voter</button>
        @error('staff_id')<p class="md:col-span-4 text-sm text-red-700">{{ $message }}</p>@enderror
        @error('phone')<p class="md:col-span-4 text-sm text-red-700">{{ $message }}</p>@enderror
    </form>

    <form method="POST" action="{{ route('ec.voters.import') }}" enctype="multipart/form-data" class="panel mb-5 rounded-[24px] border border-[#c4a35a]/15 p-5">
        @csrf
        <p class="text-sm text-[#5c5346]">CSV columns: staff_id, name, phone. Phone numbers are stored internally and shown masked only.</p>
        <div class="mt-3 flex flex-wrap gap-3">
            <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
            <button class="rounded-full bg-[#12352c] px-4 py-2 text-[#f4efe6]">Import CSV</button>
        </div>
        @error('file')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </form>
@endunless

<div class="panel overflow-x-auto rounded-[28px] border border-[#c4a35a]/15">
    <table class="w-full text-left text-sm">
        <thead class="bg-[#f4efe6] text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">
            <tr>
                <th class="px-4 py-3">Staff ID</th>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3">Ballot</th>
                <th class="px-4 py-3">Serial</th>
                <th class="px-4 py-3 no-print"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($voters as $voter)
                <tr class="border-t border-[#c4a35a]/10">
                    <td class="px-4 py-3 font-mono">{{ $voter->staff_id }}</td>
                    <td class="px-4 py-3">{{ $voter->name }}</td>
                    <td class="px-4 py-3">{{ $voter->maskedPhone() }}</td>
                    <td class="px-4 py-3">{{ $voter->voteChannelLabel() }}</td>
                    <td class="px-4 py-3 font-mono tracking-wider">{{ $voter->paperBallotSerial?->formatted() ?? '—' }}</td>
                    <td class="px-4 py-3 no-print">
                        <div class="flex flex-wrap gap-3">
                            @if (! $voter->hasVoted() && $election && ! $election->isPublished())
                                <form method="POST" action="{{ route('ec.voters.paper', $voter) }}" onsubmit="return confirm('Issue a paper ballot for {{ $voter->name }} ({{ $voter->staff_id }})? This locks the digital ballot and prints a QR serial sheet.')">
                                    @csrf
                                    <button class="text-[#12352c]">Paper vote</button>
                                </form>
                            @elseif ($voter->votedOnPaper() && $election && ! $election->isPublished())
                                <form method="POST" action="{{ route('ec.voters.paper.reprint', $voter) }}" onsubmit="return confirm('Reprint the paper ballot for {{ $voter->name }} ({{ $voter->staff_id }}) with the same serial? Use this after a power cut, printer jam, or spoiled sheet.')">
                                    @csrf
                                    <button class="text-[#12352c]">Reprint</button>
                                </form>
                            @endif
                            @if ($election && ! $election->registerLocked() && ! $voter->hasVoted())
                                <form method="POST" action="{{ route('ec.voters.destroy', $voter) }}" onsubmit="return confirm('Remove this voter?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-800">Remove</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-[#6b6254]" colspan="6">No voters yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if (method_exists($voters, 'links'))
    <div class="mt-4">{{ $voters->links() }}</div>
@endif
@endsection
