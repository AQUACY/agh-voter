@props([
    'name',
    'photo' => null,
    'initials' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'h-10 w-10 text-[11px]',
        'md' => 'h-14 w-14 text-sm',
        'lg' => 'h-20 w-20 text-lg',
        'xl' => 'h-24 w-24 text-xl',
    ];
    $class = $sizes[$size] ?? $sizes['md'];
    $letters = $initials ?: strtoupper(collect(preg_split('/\s+/', trim($name)) ?: [])->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''));
@endphp

@if ($photo)
    <img src="{{ $photo }}" alt="{{ $name }}" {{ $attributes->merge(['class' => $class.' portrait-ring rounded-full object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => $class.' portrait-ring inline-flex items-center justify-center rounded-full bg-[var(--forest)] font-medium tracking-wide text-[var(--ivory)]']) }}>{{ $letters }}</span>
@endif
