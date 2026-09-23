@props(['current' => 'staff'])

@php
    $steps = [
        'staff' => 'Staff ID',
        'code' => 'SMS code',
        'ballot' => 'Ballot',
        'done' => 'Done',
    ];
    $keys = array_keys($steps);
    $active = array_search($current, $keys, true);
@endphp

<ol class="mb-8 flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-[var(--muted)]" data-enter>
    @foreach ($steps as $key => $label)
        @php $index = $loop->index; @endphp
        <li class="flex items-center gap-2 {{ $index === $active ? 'text-[var(--forest)]' : ($index < $active ? 'text-[var(--gold)]' : '') }}">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[10px] {{ $index <= $active ? 'bg-[var(--forest)] text-[var(--ivory)]' : 'bg-[#e8e0d2] text-[var(--muted)]' }}">{{ $index + 1 }}</span>
            <span class="hidden sm:inline">{{ $label }}</span>
        </li>
        @unless ($loop->last)
            <li class="h-px flex-1 bg-[color-mix(in_srgb,var(--gold)_30%,transparent)]" aria-hidden="true"></li>
        @endunless
    @endforeach
</ol>
