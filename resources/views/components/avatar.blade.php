@props(['user' => null, 'name' => null, 'size' => 'md'])

@php
    $label = $name ?? $user?->name ?? '?';
    $initials = collect(preg_split('/\s+/', trim($label)))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $palette = ['bg-avatar-blue', 'bg-avatar-violet', 'bg-avatar-amber'];
    $tone = $palette[crc32($label) % count($palette)];
    $dimensions = match ($size) {
        'sm' => 'size-[22px] text-[9.5px]',
        default => 'size-7 text-[11px]',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "flex flex-none items-center justify-center rounded-full font-bold text-white {$tone} {$dimensions}",
    'title' => $label,
    'aria-label' => $label,
]) }}>{{ $initials }}</span>
