{{-- Pantalla 06 — Malla CPM con actividades --}}
<x-layouts.app title="{{ $project->name }} · Malla CPM">
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
    </x-slot:topbar>

    <div class="flex h-full min-h-0">
        {{-- Lienzo de la malla --}}
        <div class="min-w-0 flex-1 overflow-auto bg-ink-50 bg-dots p-6">
            @include('projects.partials.cpm-graph', ['project' => $project, 'selected' => $selected])
        </div>

        {{-- Ficha de la actividad seleccionada --}}
        @if ($selected)
            <aside class="w-80 flex-none overflow-auto border-l border-ink-200 p-5">
                @include('projects.partials.activity-detail', ['activity' => $selected])
            </aside>
        @endif
    </div>
</x-layouts.app>
