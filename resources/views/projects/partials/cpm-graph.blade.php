@php
    /**
     * El grafo se dibuja desde grid_column / grid_row, que el CpmCalculator ya
     * calculó: la columna es la profundidad en el grafo y la fila ordena por
     * holgura, así la ruta crítica queda como una línea horizontal arriba.
     */
    $nodeWidth = 220;
    $nodeHeight = 104;
    $gapX = 64;
    $gapY = 40;

    $position = fn (int $column, int $row): array => [
        'x' => $column * ($nodeWidth + $gapX),
        'y' => $row * ($nodeHeight + $gapY),
    ];

    $columns = $project->activities->max('grid_column') + 1;
    $rows = $project->activities->max('grid_row') + 1;
    $canvasWidth = $columns * ($nodeWidth + $gapX) - $gapX;
    $canvasHeight = $rows * ($nodeHeight + $gapY) - $gapY;

    $coordinates = $project->activities->mapWithKeys(
        fn ($activity) => [$activity->id => $position($activity->grid_column, $activity->grid_row)]
    );
@endphp

<div id="cpm-scroll-viewport" class="min-h-full min-w-full overflow-auto" data-selected-activity="{{ $selected?->code }}">
<div class="relative" style="width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;">
    {{-- Aristas: cada dependencia es una curva del borde derecho al izquierdo. --}}
    <svg class="absolute inset-0 overflow-visible" width="{{ $canvasWidth }}" height="{{ $canvasHeight }}" aria-hidden="true">
        <defs>
            <marker id="arrow" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
                <path d="M0 0 L7 3.5 L0 7 Z" fill="var(--color-ink-300)" />
            </marker>
            <marker id="arrow-critical" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
                <path d="M0 0 L7 3.5 L0 7 Z" fill="var(--color-critical)" />
            </marker>
        </defs>

        @foreach ($project->activities as $activity)
            @foreach ($activity->predecessors as $predecessor)
                @php
                    $from = $coordinates[$predecessor->id];
                    $to = $coordinates[$activity->id];
                    $x1 = $from['x'] + $nodeWidth;
                    $y1 = $from['y'] + $nodeHeight / 2;
                    $x2 = $to['x'];
                    $y2 = $to['y'] + $nodeHeight / 2;
                    $midX = $x1 + ($x2 - $x1) / 2;

                    // La arista es crítica solo si ambos extremos lo son.
                    $isCritical = $activity->is_critical
                        && $predecessor->is_critical
                        && $predecessor->early_finish === $activity->early_start;
                @endphp

                <path d="M{{ $x1 }} {{ $y1 }} C {{ $midX }} {{ $y1 }}, {{ $midX }} {{ $y2 }}, {{ $x2 }} {{ $y2 }}"
                      fill="none"
                      stroke="{{ $isCritical ? 'var(--color-critical)' : 'var(--color-ink-300)' }}"
                      stroke-width="{{ $isCritical ? 2.6 : 1.8 }}"
                      marker-end="url(#{{ $isCritical ? 'arrow-critical' : 'arrow' }})" />
            @endforeach
        @endforeach
    </svg>

    {{-- Nodos --}}
    @foreach ($project->activities as $activity)
        @php $point = $coordinates[$activity->id]; @endphp

        <a id="activity-{{ $activity->code }}" data-activity-code="{{ $activity->code }}" href="{{ route('projects.show', ['project' => $project, 'activity' => $activity->code, 'view' => $view ?? 'network']) }}"
           @class([
               'absolute flex flex-col justify-between rounded-xl border-[1.5px] p-2.5 shadow-sm',
               'bg-white' => ! $activity->isCompleted() && ! $activity->isOverdue(),
               'border-critical ring-3 ring-critical/10' => ($activity->is_critical || $activity->isOverdue()) && ! $activity->isCompleted(),
               'border-ink-300' => ! $activity->is_critical && ! $activity->isOverdue() && ! $activity->isCompleted(),
               'ring-3 ring-brand-100 border-brand-500' => $selected?->is($activity),
               'border-done bg-done-soft' => $activity->isCompleted(),
               'bg-critical-soft' => $activity->isOverdue() && ! $activity->isCompleted(),
           ])
           @if ($selected?->is($activity)) aria-current="true" @endif
           data-completed="{{ $activity->isCompleted() ? 'true' : 'false' }}"
           data-overdue="{{ $activity->isOverdue() ? 'true' : 'false' }}"
           style="left: {{ $point['x'] }}px; top: {{ $point['y'] }}px; width: {{ $nodeWidth }}px; height: {{ $nodeHeight }}px;">

            <div class="flex items-center justify-between">
                <span class="num text-[10px] font-semibold text-ink-500">{{ $activity->code }}</span>
                @if ($activity->isCompleted())
                    <span class="text-[10px] font-bold text-done">✓ Hecha</span>
                @elseif ($activity->isOverdue())
                    <span class="text-[10px] font-bold text-critical">Atrasada</span>
                @endif
                <span @class([
                    'num text-[11px] font-semibold',
                    'text-critical' => $activity->is_critical,
                    'text-brand-600' => ! $activity->is_critical,
                ])>{{ $activity->duration_days }} d</span>
            </div>

            <p class="line-clamp-3 text-xs font-semibold leading-tight tracking-tight">{{ $activity->name }}</p>

            <div class="flex items-center justify-between">
                <span class="num flex gap-1.5 text-[10px] text-ink-500">
                    <span>ES {{ $activity->early_start }}</span>
                    <span>EF {{ $activity->early_finish }}</span>
                </span>
                <span @class([
                    'rounded-full px-1.5 py-0.5 text-[9px] font-semibold',
                    'bg-critical/10 text-critical' => $activity->is_critical || $activity->isOverdue(),
                    'bg-slack/10 text-slack' => ! $activity->is_critical && ! $activity->isOverdue(),
                ])>
                    {{ $activity->isOverdue() ? 'atrasada' : ($activity->is_critical ? 'crítica' : 'holgura '.$activity->slack) }}
                </span>
            </div>
        </a>
    @endforeach
</div>
</div>

{{-- Leyenda: la ruta crítica se distingue por color y por grosor de trazo. --}}
<div class="mt-6 flex gap-4 text-xs text-ink-500">
    <span class="flex items-center gap-1.5">
        <span class="h-1 w-4 rounded-full bg-critical"></span>
        Ruta crítica · {{ $project->total_duration_days }} d
    </span>
    <span class="flex items-center gap-1.5">
        <span class="h-0.5 w-4 rounded-full bg-ink-300"></span>
        Dependencia con holgura
    </span>
</div>
