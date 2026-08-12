@props(['name'])

<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes->merge(['class' => 'size-3.5 flex-none']) }}>
    @switch($name)
        @case('projects')
            <rect x="2" y="2.5" width="12" height="11" rx="2.5" />
            <path d="M2 6h12M6 6v7.5" />
            @break

        @case('activities')
            <path d="M2.5 4h11M2.5 8h11M2.5 12h7" />
            @break

        @case('calendar')
            <rect x="2" y="3" width="12" height="11" rx="2.5" />
            <path d="M2 6.5h12M5.5 1.8v2.4M10.5 1.8v2.4" />
            @break

        @case('plan')
            <path d="M8 1.8 9.9 5.6l4.1.6-3 2.9.7 4.1L8 11.3l-3.7 1.9.7-4.1-3-2.9 4.1-.6z" />
            @break

        @case('settings')
            <circle cx="8" cy="8" r="2.2" />
            <path d="M8 1.6v1.6M8 12.8v1.6M14.4 8h-1.6M3.2 8H1.6M12.5 3.5l-1.1 1.1M4.6 11.4l-1.1 1.1M12.5 12.5l-1.1-1.1M4.6 4.6 3.5 3.5" />
            @break

        @case('search')
            <circle cx="7.2" cy="7.2" r="4.4" />
            <path d="M10.6 10.6 13.5 13.5" />
            @break

        @case('plus')
            <path d="M8 3.2v9.6M3.2 8h9.6" />
            @break
    @endswitch
</svg>
