@php
    use App\Enums\ProjectStatus;

    $completion = $project->activities_count > 0
        ? (int) round($project->completed_activities_count / $project->activities_count * 100)
        : 0;

    $isOverdue = $project->isOverdue();
    $daysBehind = $project->daysBehindSchedule();

    [$badgeLabel, $badgeClasses, $barClasses] = match (true) {
        $project->status === ProjectStatus::Draft => ['Borrador', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        in_array($project->status, [ProjectStatus::Clarifying, ProjectStatus::AwaitingInput], true) => ['Aclarando', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        $project->status === ProjectStatus::Generating => ['Generando', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        $project->status === ProjectStatus::Failed => ['Falló', 'bg-critical/10 text-critical', 'bg-critical'],
        $completion === 100 => ['Completado', 'bg-done/10 text-done', 'bg-done'],
        $isOverdue => ['Atrasado '.$daysBehind.' d', 'bg-critical/10 text-critical', 'bg-critical'],
        default => ['En plazo', 'bg-done/10 text-done', 'bg-brand-500'],
    };

    $target = $project->status === ProjectStatus::Ready
        ? route('projects.show', $project)
        : route('projects.generating', $project);
@endphp

<a href="{{ $target }}" class="flex flex-col gap-3 rounded-xl border border-ink-200 p-4 hover:border-ink-300 hover:shadow-sm">
    <div class="flex items-start justify-between gap-2.5">
        <div class="min-w-0">
            <h3 class="truncate text-[15px] font-semibold tracking-tight">{{ $project->name }}</h3>
            <p class="mt-0.5 text-xs text-ink-500">
                {{ $project->type->label() }} · {{ $project->activities_count }} {{ Str::plural('actividad', $project->activities_count) }}
            </p>
        </div>
        <span class="flex-none rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClasses }}">
            {{ $badgeLabel }}
        </span>
    </div>

    <div class="flex items-baseline justify-between">
        <span class="num text-sm font-semibold">{{ $completion }}%</span>
        <span class="num text-xs {{ $isOverdue ? 'text-critical' : 'text-ink-500' }}">
            {{ $project->deadline?->translatedFormat('d M Y') ?? 'Sin fecha' }}
        </span>
    </div>

    <div class="h-1.5 overflow-hidden rounded-full bg-ink-100">
        <div class="h-full rounded-full {{ $barClasses }}" style="width: {{ max($completion, 2) }}%"></div>
    </div>

    <p class="num text-xs text-ink-500">
        @if ($project->total_duration_days)
            {{ $project->total_duration_days }} d · {{ $project->critical_activities_count }} en ruta crítica
        @else
            Falta generar la malla
        @endif
    </p>
</a>
