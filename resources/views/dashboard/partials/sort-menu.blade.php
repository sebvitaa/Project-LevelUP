@php use App\Enums\DashboardSort; @endphp

<details class="relative">
    <summary class="flex h-[34px] cursor-pointer list-none items-center gap-1.5 rounded-lg border border-ink-300 bg-white px-3.5 text-[13px] font-semibold text-ink-700 hover:bg-ink-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">
        Ordenar: {{ $sort->label() }}
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" class="size-3 text-ink-400" aria-hidden="true">
            <path d="M4 6.5 8 10.5l4-4" />
        </svg>
    </summary>

    <div class="absolute right-0 z-20 mt-1.5 flex w-48 flex-col rounded-xl border border-ink-200 bg-white p-1 shadow-card">
        @foreach (DashboardSort::cases() as $case)
            <a href="{{ route('dashboard', array_filter([
                    'filtro' => $filter->value,
                    'orden' => $case->value,
                    'q' => $search,
                ])) }}"
               @class([
                   'flex h-8 items-center rounded-lg px-2.5 text-[13px]',
                   'bg-brand-50 font-semibold text-brand-600' => $case === $sort,
                   'text-ink-700 hover:bg-ink-50' => $case !== $sort,
               ])>
                {{ $case->label() }}
            </a>
        @endforeach
    </div>
</details>
