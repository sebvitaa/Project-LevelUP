{{-- Pantalla 02 — Dashboard de proyectos y completaciones --}}
<x-layouts.app title="Mis proyectos · Project LevelUp">
    <x-slot:topbar>
        <h1 class="text-[15.5px] font-bold tracking-tight">Mis proyectos</h1>
        <span class="flex h-[22px] items-center rounded-full bg-ink-100 px-2.5 text-[11px] font-semibold text-ink-500">
            {{ $totalProjects }} {{ trans_choice('{1} proyecto|[0,*] proyectos', $totalProjects) }}
        </span>
        <div class="flex-1"></div>

        <form method="GET" action="{{ route('dashboard') }}" role="search"
              class="flex h-8 w-full max-w-[280px] items-center gap-[7px] rounded-lg border border-ink-200 bg-ink-50 px-[11px] focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-100">
            <input type="hidden" name="filtro" value="{{ $filter->value }}">
            <input type="hidden" name="orden" value="{{ $sort->value }}">
            <x-nav-icon name="search" class="size-3.5 flex-none text-ink-400" />
            <label for="dashboard-search" class="sr-only">Buscar proyecto o actividad</label>
            <input id="dashboard-search" type="search" name="q" value="{{ $search }}" maxlength="120"
                   placeholder="Buscar proyecto o actividad…"
                   class="w-full bg-transparent text-[12.5px] text-ink-900 placeholder:text-ink-400 focus:outline-none">
            <button type="submit" class="sr-only">Buscar</button>
        </form>

        <a href="{{ route('projects.create.type') }}"
           class="flex h-[34px] flex-none items-center gap-[7px] rounded-lg bg-brand-500 px-3.5 text-[13px] font-semibold text-white shadow-[0_1px_2px_rgba(43,87,246,0.3)] hover:bg-brand-600">
            <x-nav-icon name="plus" class="size-3.5" />
            Nuevo proyecto
        </a>
        <x-avatar :user="auth()->user()" class="ml-1" />
    </x-slot:topbar>

    <div class="flex flex-col gap-5 p-6">
        @include('dashboard.partials.kpi-row', ['summary' => $summary])

        <div class="flex flex-wrap items-center gap-2.5">
            <h2 class="text-[15px] font-bold tracking-tight">Proyectos</h2>
            @if ($search !== '')
                <span class="text-xs text-ink-500">Resultados para “{{ $search }}”</span>
                <a href="{{ route('dashboard', ['filtro' => $filter->value, 'orden' => $sort->value]) }}"
                   class="text-xs font-semibold text-brand-500 hover:text-brand-600">Limpiar búsqueda</a>
            @endif
            <div class="flex-1"></div>
            @include('dashboard.partials.filter-tabs', compact('filter', 'sort', 'search'))
            @include('dashboard.partials.sort-menu', compact('filter', 'sort', 'search'))
        </div>

        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                @include('dashboard.partials.project-card', ['project' => $project])
            @endforeach

            @if ($projects->isEmpty())
                <p class="col-span-full rounded-xl border border-dashed border-ink-300 bg-ink-50 p-6 text-center text-[13px] text-ink-500">
                    @if ($totalProjects === 0)
                        Todavía no tienes proyectos. Empieza describiendo uno y la IA arma la malla.
                    @elseif ($search !== '')
                        No encontramos proyectos ni actividades para “{{ $search }}”.
                    @else
                        Ningún proyecto coincide con “{{ $filter->label() }}”.
                    @endif
                </p>
            @endif

            <a href="{{ route('projects.create.type') }}"
               class="flex min-h-[164px] flex-col items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-ink-300 bg-ink-50 text-ink-500 hover:border-brand-500 hover:bg-brand-50">
                <span class="flex size-[34px] items-center justify-center rounded-[9px] bg-brand-500 text-xl leading-none text-white">+</span>
                <span class="text-[13px] font-semibold text-ink-700">Nuevo proyecto</span>
                <span class="text-[11.5px]">Describe tu idea y la IA arma el CPM</span>
            </a>
        </div>
    </div>
</x-layouts.app>
