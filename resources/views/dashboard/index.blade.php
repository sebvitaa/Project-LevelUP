{{-- Pantalla 02 — Dashboard de proyectos y completaciones --}}
<x-layouts.app title="Mis proyectos · Project LevelUp">
    <x-slot:topbar>
        <h1 class="text-base font-semibold tracking-tight">Mis proyectos</h1>
        <span class="rounded-full bg-ink-100 px-2.5 py-0.5 text-xs font-semibold text-ink-500">
            {{ $projects->count() }} {{ Str::plural('activo', $projects->count()) }}
        </span>
        <div class="flex-1"></div>
        <a href="{{ route('projects.create.type') }}"
           class="flex h-9 items-center rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">
            Nuevo proyecto
        </a>
    </x-slot:topbar>

    <div class="flex flex-col gap-5 p-6">
        {{-- Resumen: lo agregado antes que el detalle. --}}
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex flex-col gap-1.5 rounded-xl border border-ink-200 p-4">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Avance promedio</span>
                <span class="num text-2xl font-semibold tracking-tight">{{ $summary['average_completion'] }}%</span>
            </div>
            <div class="flex flex-col gap-1.5 rounded-xl border border-ink-200 p-4">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Actividades completadas</span>
                <span class="num text-2xl font-semibold tracking-tight">
                    {{ $summary['completed_activities'] }}<span class="text-base text-ink-500">/{{ $summary['total_activities'] }}</span>
                </span>
            </div>
            <div class="flex flex-col gap-1.5 rounded-xl border border-ink-200 p-4">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">En ruta crítica</span>
                <span class="num text-2xl font-semibold tracking-tight text-critical">{{ $summary['critical_activities'] }}</span>
                <span class="text-xs text-ink-500">Sin holgura: cualquier atraso mueve la entrega.</span>
            </div>
            <div class="flex flex-col gap-1.5 rounded-xl border border-ink-200 p-4">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Próxima entrega</span>
                @if ($summary['next_deadline'])
                    <span class="num text-xl font-semibold tracking-tight">
                        {{ $summary['next_deadline']->deadline->translatedFormat('d M') }}
                    </span>
                    <span class="text-xs text-ink-500">{{ $summary['next_deadline']->name }}</span>
                @else
                    <span class="text-xl font-semibold tracking-tight text-ink-500">—</span>
                    <span class="text-xs text-ink-500">Ningún proyecto tiene fecha límite.</span>
                @endif
            </div>
        </div>

        <h2 class="text-[15px] font-semibold tracking-tight">Proyectos activos</h2>

        <div class="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $project)
                @include('dashboard.partials.project-card', ['project' => $project])
            @empty
                <p class="col-span-full text-sm text-ink-500">
                    Todavía no tienes proyectos. Empieza con
                    <a href="{{ route('projects.create.type') }}" class="font-semibold text-brand-500">uno nuevo</a>.
                </p>
            @endforelse

            <a href="{{ route('projects.create.type') }}"
               class="flex min-h-40 flex-col items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-ink-300 bg-ink-50 text-ink-500 hover:border-brand-500 hover:bg-brand-50">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-500 text-xl text-white">+</span>
                <span class="text-sm font-semibold text-ink-700">Nuevo proyecto</span>
                <span class="text-xs">Describe tu idea y la IA arma el CPM</span>
            </a>
        </div>
    </div>
</x-layouts.app>
