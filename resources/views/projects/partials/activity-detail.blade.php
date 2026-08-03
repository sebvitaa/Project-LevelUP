<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-2 border-b border-ink-200 pb-4">
        <div class="flex items-center justify-between">
            <span class="num text-[11px] font-semibold uppercase tracking-wider text-ink-500">
                Actividad {{ $activity->code }}
            </span>
            <span @class([
                'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                'bg-critical/10 text-critical' => $activity->is_critical,
                'bg-slack/10 text-slack' => ! $activity->is_critical,
            ])>
                {{ $activity->is_critical ? 'Ruta crítica' : 'Holgura '.$activity->slack.' d' }}
            </span>
        </div>

        <h2 class="text-[15px] font-semibold leading-tight tracking-tight">{{ $activity->name }}</h2>

        <div class="flex flex-wrap gap-1.5">
            <span class="num rounded-full bg-ink-100 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">
                {{ $activity->duration_days }} días
            </span>
            @if ($activity->startDate())
                <span class="num rounded-full bg-ink-100 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">
                    {{ $activity->startDate()->translatedFormat('d M') }} → {{ $activity->finishDate()->translatedFormat('d M') }}
                </span>
            @endif
        </div>
    </div>

    @if ($activity->description)
        <div class="flex flex-col gap-1.5">
            <span class="text-[10px] font-bold uppercase tracking-widest text-ink-500">Descripción</span>
            <p class="text-xs leading-relaxed text-ink-700">{{ $activity->description }}</p>
        </div>
    @endif

    <div class="flex flex-col gap-2">
        <span class="text-[10px] font-bold uppercase tracking-widest text-ink-500">Tiempos calculados</span>
        <div class="grid grid-cols-2 gap-2">
            @foreach ([
                'Inicio temprano' => $activity->early_start,
                'Fin temprano' => $activity->early_finish,
                'Inicio tardío' => $activity->late_start,
                'Fin tardío' => $activity->late_finish,
            ] as $label => $value)
                <div class="rounded-lg border border-ink-200 bg-ink-50 px-2.5 py-1.5">
                    <div class="text-[9px] font-semibold uppercase tracking-wider text-ink-500">{{ $label }}</div>
                    <div class="num text-sm font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div @class([
            'rounded-lg px-2.5 py-1.5',
            'bg-critical/10' => $activity->is_critical,
            'bg-ink-50 border border-ink-200' => ! $activity->is_critical,
        ])>
            <div class="text-[9px] font-semibold uppercase tracking-wider {{ $activity->is_critical ? 'text-critical' : 'text-ink-500' }}">
                Holgura
            </div>
            <div class="num text-sm font-semibold {{ $activity->is_critical ? 'text-critical' : '' }}">
                {{ $activity->slack }} días
            </div>
        </div>
    </div>

    @if ($activity->predecessors->isNotEmpty())
        <div class="flex flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-widest text-ink-500">Precedentes</span>
            @foreach ($activity->predecessors as $predecessor)
                <div class="flex items-center gap-2 py-1 text-xs text-ink-700">
                    <span class="num rounded bg-ink-100 px-1.5 text-[10px] font-semibold text-ink-500">{{ $predecessor->code }}</span>
                    <span class="truncate">{{ $predecessor->name }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($activity->successors->isNotEmpty())
        <div class="flex flex-col gap-1">
            <span class="text-[10px] font-bold uppercase tracking-widest text-ink-500">Sucesoras</span>
            @foreach ($activity->successors as $successor)
                <div class="flex items-center gap-2 py-1 text-xs text-ink-700">
                    <span class="num rounded bg-ink-100 px-1.5 text-[10px] font-semibold text-ink-500">{{ $successor->code }}</span>
                    <span class="truncate">{{ $successor->name }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('activities.toggle', $activity) }}" class="mt-2">
        @csrf
        <button type="submit" @class([
            'h-9 w-full rounded-lg text-sm font-semibold',
            'border border-ink-300 text-ink-700 hover:bg-ink-50' => $activity->isCompleted(),
            'bg-brand-500 text-white hover:bg-brand-600' => ! $activity->isCompleted(),
        ])>
            {{ $activity->isCompleted() ? 'Marcar pendiente' : 'Marcar hecha' }}
        </button>
    </form>
</div>
