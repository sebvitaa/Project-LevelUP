{{-- Pantalla 02 — Dashboard de proyectos y completaciones --}}
<x-layouts.app title="Mis proyectos · Project LevelUp">
    <x-slot:topbar>
        <h1 class="text-[15.5px] font-bold tracking-tight">Mis proyectos</h1>

        <span class="flex h-[22px] items-center rounded-full bg-ink-100 px-2.5 text-[11px] font-semibold text-ink-500">
            {{ $totalProjects }} {{ trans_choice('{1} activo|[2,*] activos', $totalProjects) }}
        </span>

        <div class="flex-1"></div>

        <label class="flex h-8 w-full max-w-[260px] items-center gap-[7px] rounded-lg border border-ink-200 bg-ink-50 px-[11px] focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-100">
            <x-nav-icon name="search" class="size-3.5 flex-none text-ink-400" />
            <input type="search" name="q" placeholder="Buscar proyecto o actividad…"
                   class="w-full bg-transparent text-[12.5px] text-ink-900 placeholder:text-ink-400 focus:outline-none">
        </label>

        <a href="{{ route('projects.create.type') }}"
           class="flex h-[34px] flex-none items-center gap-[7px] rounded-lg bg-brand-500 px-3.5 text-[13px] font-semibold text-white shadow-[0_1px_2px_rgba(43,87,246,0.3)] hover:bg-brand-600">
            <x-nav-icon name="plus" class="size-3.5" />
            Nuevo proyecto
        </a>

        <x-avatar :user="auth()->user()" class="ml-1" />
    </x-slot:topbar>

    <div class="flex flex-col gap-5 p-6">
        {{-- Resumen antes que detalle: la fila de KPI responde "¿cómo voy?". --}}
        @include('dashboard.partials.kpi-row', ['summary' => $summary])

        <div class="flex flex-wrap items-center gap-2.5">
            <h2 class="text-[15px] font-bold tracking-tight">Proyectos activos</h2>
            <div class="flex-1"></div>
            @include('dashboard.partials.filter-tabs', ['filter' => $filter, 'sort' => $sort])
            @include('dashboard.partials.sort-menu', ['filter' => $filter, 'sort' => $sort])
        </div>

        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                @include('dashboard.partials.project-card', ['project' => $project])
            @endforeach

            @if ($projects->isEmpty())
                <p class="col-span-full rounded-xl border border-dashed border-ink-300 bg-ink-50 p-6 text-center text-[13px] text-ink-500">
                    @if ($totalProjects === 0)
                        Todavía no tienes proyectos. Empieza describiendo uno y la IA arma la malla.
                    @else
                        Ningún proyecto calza con «{{ $filter->label() }}».
                        <a href="{{ route('dashboard') }}" class="font-semibold text-brand-500 hover:text-brand-600">Ver todos</a>.
                    @endif
                </p>
            @endif

            {{-- La tarjeta de creación cierra la grilla, como en el mockup. --}}
            <a href="{{ route('projects.create.type') }}"
               class="flex min-h-[164px] flex-col items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-ink-300 bg-ink-50 text-ink-500 hover:border-brand-500 hover:bg-brand-50">
                <span class="flex size-[34px] items-center justify-center rounded-[9px] bg-brand-500 text-xl leading-none text-white">+</span>
                <span class="text-[13px] font-semibold text-ink-700">Nuevo proyecto</span>
                <span class="text-[11.5px]">Describe tu idea y la IA arma el CPM</span>
            </a>
        </div>
    </div>
</x-layouts.app>
