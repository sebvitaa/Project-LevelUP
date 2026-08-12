{{-- Pantalla 06 — Malla CPM con actividades --}}
<x-layouts.app title="{{ $project->name }} · {{ $view === 'gantt' ? 'Gantt' : 'Malla CPM' }}">
    <x-slot:topbar>
        <h1 class="text-base font-semibold tracking-tight">{{ $project->name }}</h1>
        <span @class([
            'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
            'bg-critical/10 text-critical' => $project->isOverdue(),
            'bg-done/10 text-done' => ! $project->isOverdue(),
        ])>
            {{ $project->isOverdue() ? 'Atrasado '.$project->daysBehindSchedule().' d' : 'En plazo' }}
        </span>
        <span class="num text-xs text-ink-500">
            Duración total <b class="text-ink-900">{{ $project->total_duration_days }} días</b>
            · término {{ $project->projectedFinishDate()?->translatedFormat('d M Y') }}
        </span>
        <div class="flex-1"></div>
        <span class="rounded-full bg-critical/10 px-2.5 py-0.5 text-[11px] font-semibold text-critical">
            Ruta crítica: {{ $project->activities->where('is_critical', true)->count() }} de {{ $project->activities->count() }}
        </span>
        <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm(@js('Eliminar permanentemente el proyecto '.$project->name.' y todas sus actividades?'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-critical/30 px-3 py-1.5 text-xs font-semibold text-critical hover:bg-critical/10" aria-label="Eliminar proyecto {{ $project->name }}">
                Eliminar
            </button>
        </form>
    </x-slot:topbar>

    <div class="flex h-full min-h-0 flex-col">
        <nav class="flex flex-none gap-1 border-b border-ink-200 px-6 pt-3" aria-label="Vista del cronograma">
            <a href="{{ route('projects.show', ['project' => $project, 'view' => 'network', 'activity' => $selected?->code]) }}" @class(['border-b-2 border-brand-500 px-3 py-2 text-sm font-semibold text-brand-600' => $view === 'network', 'px-3 py-2 text-sm font-semibold text-ink-500 hover:text-ink-900' => $view !== 'network'])>Malla</a>
            <a href="{{ route('projects.show', ['project' => $project, 'view' => 'gantt', 'activity' => $selected?->code]) }}" @class(['border-b-2 border-brand-500 px-3 py-2 text-sm font-semibold text-brand-600' => $view === 'gantt', 'px-3 py-2 text-sm font-semibold text-ink-500 hover:text-ink-900' => $view !== 'gantt'])>Gantt</a>
            <span class="cursor-not-allowed px-3 py-2 text-sm font-semibold text-ink-300" aria-disabled="true">Lista</span>
        </nav>
    <div class="flex min-h-0 flex-1">
        {{-- Lienzo de la malla --}}
        <div class="min-w-0 flex-1 overflow-auto bg-ink-50 bg-dots p-6">
            @if ($view === 'gantt')
                @include('projects.partials.gantt-chart', ['project' => $project, 'timeline' => $timeline, 'selected' => $selected, 'view' => $view])
            @else
                @include('projects.partials.cpm-graph', ['project' => $project, 'selected' => $selected, 'view' => $view])
            @endif
        </div>

        {{-- Ficha de la actividad seleccionada --}}
        @if ($selected)
            <aside class="w-80 flex-none overflow-auto border-l border-ink-200 p-5">
                @include('projects.partials.activity-detail', ['activity' => $selected, 'view' => $view])
            </aside>
        @endif
    </div>
    </div>
</x-layouts.app>
