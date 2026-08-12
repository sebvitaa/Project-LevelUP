<div class="min-w-[760px]" aria-label="Carta Gantt de {{ $project->name }}">
    @php
        $scaleLabel = ['day' => 'diaria', 'week' => 'semanal', 'month' => 'mensual'][$timeline['scale']] ?? $timeline['scale'];
    @endphp
    <div class="mb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight">Carta Gantt</h2>
            <p class="text-xs text-ink-500">{{ $timeline['start_date'] }} → {{ $timeline['last_date'] }} · escala {{ $scaleLabel }}</p>
        </div>
        @if ($timeline['deadline'])
            <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-critical/10 text-critical' => $project->isOverdue(), 'bg-done/10 text-done' => ! $project->isOverdue()])>Deadline: {{ $timeline['deadline']['date'] }}</span>
        @endif
    </div>

    <div class="overflow-auto rounded-xl border border-ink-200 bg-white">
        <div class="grid" style="grid-template-columns: 320px {{ $timeline['timeline_width'] }}px;">
            <div class="sticky left-0 top-0 z-20 border-b border-r border-ink-200 bg-white p-3 text-xs font-semibold text-ink-500">Actividad</div>
            <div class="sticky top-0 z-10 relative border-b border-ink-200 bg-white" style="min-height: 48px;">
                @foreach ($timeline['months'] as $month)
                    <div class="absolute top-0 border-r border-ink-200 px-2 py-2 text-[10px] font-semibold uppercase text-ink-500" style="left: {{ ($month['offset'] / $timeline['total_days']) * 100 }}%; width: {{ ($month['duration'] / $timeline['total_days']) * 100 }}%;">{{ $month['label'] }}</div>
                @endforeach
                @foreach ($timeline['columns'] as $column)
                    @if ($timeline['scale'] === 'day' || $loop->index % 2 === 0)
                        <span class="absolute bottom-1 text-[9px] text-ink-500" style="left: {{ ($column['offset'] / $timeline['total_days']) * 100 }}%;">{{ $column['label'] }}</span>
                    @endif
                @endforeach
                @if ($timeline['deadline'] && $timeline['deadline']['within_horizon'])
                    <span class="absolute inset-y-0 z-[2] w-px bg-critical/70" style="left: {{ $timeline['deadline']['position'] }}%;" title="Deadline {{ $timeline['deadline']['date'] }}"></span>
                @endif
            </div>

            @foreach ($timeline['rows'] as $row)
                @php $activityUrl = route('projects.show', ['project' => $project, 'view' => 'gantt', 'activity' => $row['code']]); @endphp
                <a href="{{ $activityUrl }}" @class(['sticky left-0 z-10 flex min-h-14 items-center gap-2 border-b border-r border-ink-200 px-3', 'bg-white' => ! $row['is_completed'] && ! $row['is_overdue'] && ! $row['is_critical'], 'bg-done-soft' => $row['is_completed'], 'bg-critical-soft' => $row['is_overdue'] && ! $row['is_completed'], 'bg-slack-soft' => $row['is_critical'] && ! $row['is_overdue'] && ! $row['is_completed'], 'ring-2 ring-inset ring-brand-500' => $selected?->code === $row['code']]) @if ($selected?->code === $row['code']) aria-current="true" @endif>
                    <span class="num w-8 text-xs font-semibold text-ink-500">{{ $row['code'] }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold">{{ $row['name'] }}</span>
                        <span class="block truncate text-[9px] text-ink-500">{{ $row['predecessors'] ? 'Después de '.implode(', ', $row['predecessors']) : 'Sin precedentes' }}</span>
                    </span>
                    <span class="num text-[10px] text-ink-500">{{ $row['duration_days'] }}d</span>
                </a>
                <div class="relative min-h-14 border-b border-ink-100 bg-ink-50/30">
                    @foreach ($timeline['weekend_ranges'] as $weekend)
                        <span class="absolute inset-y-0 bg-ink-100/60" style="left: {{ ($weekend['offset'] / $timeline['total_days']) * 100 }}%; width: {{ ($weekend['duration'] / $timeline['total_days']) * 100 }}%;"></span>
                    @endforeach
                    @foreach ($timeline['columns'] as $column)
                        <span class="absolute inset-y-0 border-r border-ink-100" style="left: {{ ($column['offset'] / $timeline['total_days']) * 100 }}%; width: {{ ($column['duration'] / $timeline['total_days']) * 100 }}%;"></span>
                    @endforeach
                    @if ($timeline['today']['within_horizon'])
                        <span class="absolute inset-y-0 z-[1] w-px bg-brand-500/60" style="left: {{ $timeline['today']['position'] }}%;"></span>
                    @endif
                    @if ($timeline['deadline'] && $timeline['deadline']['within_horizon'])
                        <span class="absolute inset-y-0 z-[1] w-px bg-critical/40" style="left: {{ $timeline['deadline']['position'] }}%;"></span>
                    @endif
                    <a href="{{ $activityUrl }}" @class(['absolute z-[2] top-3 inline-flex h-8 items-center overflow-hidden whitespace-nowrap rounded-md px-2 text-[10px] font-semibold leading-8 text-white shadow-sm transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500', 'bg-critical' => $row['is_overdue'] && ! $row['is_completed'], 'bg-slack' => $row['is_critical'] && ! $row['is_overdue'] && ! $row['is_completed'], 'bg-done' => $row['is_completed'], 'bg-brand-500' => ! $row['is_critical'] && ! $row['is_overdue'] && ! $row['is_completed']]) style="left: {{ ($row['offset'] / $timeline['total_days']) * 100 }}%; width: {{ ($row['duration'] / $timeline['total_days']) * 100 }}%; min-width: 12px;" title="{{ $row['name'] }}: {{ $row['start_date'] }} a {{ $row['finish_date'] }}" aria-label="Abrir {{ $row['name'] }}, {{ $row['duration_days'] }} días, desde {{ $row['start_date'] }} hasta {{ $row['finish_date'] }}{{ $row['is_completed'] ? ', completada' : '' }}{{ $row['is_overdue'] ? ', atrasada' : '' }}{{ $row['is_critical'] ? ', ruta crítica' : '' }}">{{ $row['is_completed'] ? '✓ ' : '' }}{{ $row['is_overdue'] ? 'Atrasada' : ($row['is_critical'] ? 'Crítica' : 'Actividad') }} · {{ $row['duration_days'] }}d</a>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-4 text-xs text-ink-500">
        <span><i class="mr-1 inline-block size-2 rounded-full bg-slack"></i>Ruta crítica</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-critical"></i>Atrasada</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-brand-500"></i>Actividad</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-done"></i>Completada</span>
        @if ($timeline['today']['within_horizon']) <span class="text-brand-600">Hoy: {{ $timeline['today']['date'] }}</span> @endif
    </div>
</div>
