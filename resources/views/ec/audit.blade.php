@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
<div class="mb-8 flex items-end justify-between">
    <div>
        <p class="kicker">Integrity</p>
        <h1 class="display mt-2 text-5xl font-semibold text-[var(--ink)]">Audit log</h1>
    </div>
    <a class="text-sm text-[#12352c] underline-offset-4 hover:underline" href="{{ route('ec.dashboard') }}">Back to results</a>
</div>
<div class="panel overflow-x-auto rounded-[28px] border border-[#c4a35a]/15">
    <table class="w-full text-left text-sm">
        <thead class="bg-[#f4efe6] text-[11px] uppercase tracking-[0.16em] text-[#6b6254]">
            <tr>
                <th class="px-4 py-3">Time</th>
                <th class="px-4 py-3">Actor</th>
                <th class="px-4 py-3">Action</th>
                <th class="px-4 py-3">Meta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr class="border-t border-[#c4a35a]/10">
                    <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at }}</td>
                    <td class="px-4 py-3">{{ $log->actor_type }} #{{ $log->actor_id }}</td>
                    <td class="px-4 py-3">{{ $log->action }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ json_encode($log->meta) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
