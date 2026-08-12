@php
    use App\Enums\ProjectStatus;

    $completion = $project->completionPercentage();
    $daysBehind = $project->daysBehindSchedule();

    [$badgeLabel, $badgeClasses, $barClasses] = match (true) {
        $project->status === ProjectStatus::Draft => ['Borrador', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        $project->status === ProjectStatus::Clarifying => ['Analizando brief', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        $project->status === ProjectStatus::AwaitingInput => ['Esperando respuestas', 'bg-slack-soft text-slack', 'bg-slack'],
        $project->status === ProjectStatus::Generating => ['Generando', 'bg-brand-100 text-brand-600', 'bg-brand-500'],
        $project->status === ProjectStatus::Failed => ['Falló la generación', 'bg-critical-soft text-critical', 'bg-critical'],
        $project->isCompleted() => ['Completado', 'bg-done-soft text-done', 'bg-done'],
        $project->isOverdue() => ['Atrasado '.$daysBehind.' d', 'bg-critical-soft text-critical', 'bg-critical'],
        $project->isAtRisk() => ['Holgura baja', 'bg-slack-soft text-slack', 'bg-slack'],
        default => ['En plazo', 'bg-done-soft text-done', 'bg-brand-500'],
    };

    $target = $project->status === ProjectStatus::Ready
        ? route('projects.show', $project)
        : route('projects.generating', $project);
@endphp

<a href="{{ $target }}"
   class="flex flex-col gap-3 rounded-xl border border-ink-200 bg-white p-4 transition hover:border-ink-300 hover:shadow-card">
    <div class="flex items-start justify-between gap-2.5">
        <div class="min-w-0">
            <h3 class="truncate text-[14.5px] font-semibold leading-tight tracking-tight">{{ $project->name }}</h3>
            <p class="mt-[3px] text-[11.5px] text-ink-400">
                {{ $project->type->label() }} ·
                {{ $project->activities_count }} {{ trans_choice('{1} actividad|[0,*] actividades', $project->activities_count) }}
            </p>
        </div>
        <span class="flex h-[22px] flex-none items-center gap-[5px] rounded-full px-2.5 text-[11px] font-semibold {{ $badgeClasses }}">
            <span class="size-1.5 rounded-full bg-current"></span>
            {{ $badgeLabel }}
        </span>
    </div>

    <div class="flex items-baseline justify-between">
        <span class="num text-[12.5px] font-semibold">{{ $completion }}%</span>
        <span class="num text-[11.5px] {{ $project->isOverdue() ? 'text-critical' : 'text-ink-400' }}">
            {{ $project->deadline?->translatedFormat('j M Y') ?? 'Sin fecha' }}
        </span>
    </div>

    <div class="h-[5px] overflow-hidden rounded-full bg-ink-100">
        <div class="h-full rounded-full {{ $barClasses }}" style="width: {{ max($completion, 2) }}%"></div>
    </div>

    <div class="flex items-center justify-between gap-2">
        <x-avatar :user="auth()->user()" size="sm" />
        <span class="num text-[11.5px] text-ink-500">
            @if ($project->total_duration_days !== null)
                {{ $project->total_duration_days }} d · {{ $project->critical_activities_count }} en ruta crítica
            @else
                Falta generar la malla
            @endif
        </span>
    </div>
</a>
