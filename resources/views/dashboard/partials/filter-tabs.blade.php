@php use App\Enums\DashboardFilter; @endphp

<nav class="flex gap-0.5 rounded-lg bg-ink-100 p-[3px]" aria-label="Filtrar proyectos">
    @foreach (DashboardFilter::cases() as $case)
        <a href="{{ route('dashboard', array_filter([
                'filtro' => $case->value,
                'orden' => $sort->value,
                'q' => $search,
            ])) }}"
           @if ($case === $filter) aria-current="page" @endif
           @class([
               'flex h-[26px] items-center rounded-md px-3 text-[12.5px] font-semibold',
               'bg-white text-ink-900 shadow-[0_1px_2px_rgba(11,18,32,0.08)]' => $case === $filter,
               'text-ink-500 hover:text-ink-900' => $case !== $filter,
           ])>
            {{ $case->label() }}
        </a>
    @endforeach
</nav>
