@props(['current' => 1])

@php
    $steps = [1 => 'Tipo', 2 => 'Descripción', 3 => 'Malla'];
@endphp

<ol class="flex items-center gap-2.5">
    @foreach ($steps as $number => $label)
        <li @class([
            'flex items-center gap-2 text-xs font-semibold',
            'text-brand-500' => $number === $current,
            'text-ink-500' => $number < $current,
            'text-ink-300' => $number > $current,
        ])>
            <span @class([
                'num flex size-5 items-center justify-center rounded-full text-[10px]',
                'bg-brand-500 text-white' => $number === $current,
                'bg-brand-100 text-brand-600' => $number < $current,
                'border-[1.5px] border-ink-300' => $number > $current,
            ])>
                {{ $number < $current ? '✓' : $number }}
            </span>
            {{ $label }}
        </li>

        @if (! $loop->last)
            <li class="h-px w-6 bg-ink-300" aria-hidden="true"></li>
        @endif
    @endforeach
</ol>
