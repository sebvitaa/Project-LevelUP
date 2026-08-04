@php
    /**
     * Las cuatro métricas de la cartera completa. Siempre resumen todos los
     * proyectos, aunque la grilla de abajo esté filtrada.
     */
    $card = 'flex flex-col gap-1.5 rounded-xl border border-ink-200 bg-white p-4';
    $key = 'text-[11px] font-semibold uppercase tracking-[0.07em] text-ink-400';
    $value = 'num text-[26px] font-semibold leading-none tracking-tight';
    $note = 'text-[11.5px] text-ink-500';
    $pill = 'flex h-[22px] items-center gap-[5px] rounded-full px-2.5 text-[11px] font-semibold';
@endphp

<div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">

    {{-- 1 · Avance promedio, con la tendencia de las últimas 7 semanas --}}
    <div class="{{ $card }}">
        <span class="{{ $key }}">Avance promedio</span>
        <span class="{{ $value }}">
            {{ $summary['average_completion'] }}<span class="text-base text-ink-400">%</span>
        </span>
        <span class="{{ $note }}">
            @if ($summary['completion_delta'] > 0)
                +{{ $summary['completion_delta'] }} pts en las últimas 7 semanas
            @elseif ($summary['completion_delta'] < 0)
                {{ $summary['completion_delta'] }} pts en las últimas 7 semanas
            @else
                Sin cambios en las últimas 7 semanas
            @endif
        </span>
        @include('dashboard.partials.sparkline', ['points' => $summary['trend']])
    </div>

    {{-- 2 · Actividades completadas --}}
    <div class="{{ $card }}">
        <span class="{{ $key }}">Actividades completadas</span>
        <span class="{{ $value }}">
            {{ $summary['completed_activities'] }}<span class="text-base text-ink-400">/{{ $summary['total_activities'] }}</span>
        </span>
        <span class="{{ $note }}">
            {{ $summary['completed_this_week'] }} {{ trans_choice('{1} cerrada|[0,*] cerradas', $summary['completed_this_week']) }} esta semana
        </span>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100">
            <div class="h-full rounded-full bg-done" style="width: {{ $summary['average_completion'] }}%"></div>
        </div>
    </div>

    {{-- 3 · Ruta crítica: lo que no admite atraso --}}
    <div class="{{ $card }}">
        <span class="{{ $key }}">En ruta crítica</span>
        <span class="{{ $value }} text-critical">{{ $summary['critical_total'] }}</span>
        <span class="{{ $note }}">Sin holgura: cualquier atraso mueve la entrega</span>
        <div class="mt-2 flex flex-wrap gap-1">
            @if ($summary['critical_overdue'] > 0)
                <span class="{{ $pill }} bg-critical-soft text-critical">
                    <span class="size-1.5 rounded-full bg-current"></span>
                    {{ $summary['critical_overdue'] }} {{ trans_choice('{1} atrasada|[2,*] atrasadas', $summary['critical_overdue']) }}
                </span>
            @endif
            <span class="{{ $pill }} bg-slack-soft text-slack">
                {{ $summary['critical_in_progress'] }} en curso
            </span>
        </div>
    </div>

    {{-- 4 · Próxima entrega --}}
    <div class="{{ $card }}">
        <span class="{{ $key }}">Próxima entrega</span>
        @if ($summary['next_deadline'])
            @php $next = $summary['next_deadline']; @endphp
            <span class="{{ $value }} text-[22px]">{{ $next->deadline->translatedFormat('j M') }}</span>
            <span class="{{ $note }}">
                {{ $next->name }} ·
                @if ($summary['days_until_deadline'] >= 0)
                    faltan {{ $summary['days_until_deadline'] }} {{ trans_choice('{1} día|[0,*] días', $summary['days_until_deadline']) }}
                @else
                    venció hace {{ abs($summary['days_until_deadline']) }} {{ trans_choice('{1} día|[0,*] días', abs($summary['days_until_deadline'])) }}
                @endif
            </span>
            <div class="mt-2 flex gap-1">
                @if ($next->isOverdue())
                    <span class="{{ $pill }} bg-critical-soft text-critical">
                        <span class="size-1.5 rounded-full bg-current"></span>
                        Atrasado {{ $next->daysBehindSchedule() }} d
                    </span>
                @else
                    <span class="{{ $pill }} bg-done-soft text-done">
                        <span class="size-1.5 rounded-full bg-current"></span>
                        En plazo
                    </span>
                @endif
            </div>
        @else
            <span class="{{ $value }} text-[22px] text-ink-400">—</span>
            <span class="{{ $note }}">Ningún proyecto pendiente tiene fecha límite</span>
        @endif
    </div>
</div>
