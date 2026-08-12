{{-- Pantalla 05 — Generando el cronograma --}}
<x-layouts.app title="Generando · {{ $project->name }}">
    <x-slot:topbar>
        <span class="text-sm text-ink-500">
            <a href="{{ route('dashboard') }}" class="hover:text-ink-900">Mis proyectos</a>
            <span class="mx-1.5 text-ink-300">›</span>
            <span class="font-semibold text-ink-900">{{ $project->name }}</span>
        </span>
        <div class="flex-1"></div>
        @include('projects.partials.stepper', ['current' => 3])
        <div class="flex-1"></div>
    </x-slot:topbar>

    <div class="flex h-full flex-col items-center justify-center gap-7 p-10"
         data-status-url="{{ route('projects.status', $project) }}"
         data-stage="{{ $progress['stage'] }}"
         data-step-index="{{ $progress['step_index'] }}"
         id="generation-watcher">

        @if ($project->status === \App\Enums\ProjectStatus::Failed)
            <div class="flex max-w-md flex-col items-center gap-4 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">No pudimos armar la malla</h1>
                <p class="text-sm text-ink-700">{{ $project->generation_error }}</p>
                <div class="flex gap-2">
                    <a href="{{ route('dashboard') }}" class="flex h-10 items-center rounded-lg border border-ink-300 px-4 text-sm font-semibold text-ink-700">
                        Volver al dashboard
                    </a>
                    <form method="POST" action="{{ route('projects.regenerate', $project) }}">
                        @csrf
                        <button type="submit" class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">
                            Reintentar
                        </button>
                    </form>
                </div>
            </div>
        @elseif ($project->status === \App\Enums\ProjectStatus::AwaitingInput)
            @include('projects.partials.clarifications-form', [
                'project' => $project,
                'clarifications' => $pendingClarifications,
            ])
        @else
            <div class="flex flex-col items-center gap-2 text-center">
                <x-logo class="size-12 animate-pulse" />
                <h1 class="mt-4 text-2xl font-semibold tracking-tight">Armando la malla de tu proyecto</h1>
                <p class="text-sm text-ink-500">{{ $progress['message'] }}</p>
                <p class="max-w-md text-xs text-ink-500">Puedes volver más tarde; el proyecto seguirá procesándose.</p>
                @if ($progress['is_stalled'])
                    <p class="max-w-md rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Este paso está tardando más de lo esperado. No necesitas reenviar el proyecto.
                    </p>
                @endif
            </div>

            {{-- Los pasos nombran lo que está pasando; un spinner mudo no dice nada. --}}
            <ol class="flex w-96 flex-col gap-0.5">
                @foreach ([
                    'Analizando el brief',
                    'Solicitando y validando actividades',
                    'Validando dependencias',
                    'Calculando ruta crítica y holguras',
                    'Guardando la malla',
                ] as $index => $step)
                    <li @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm',
                        'font-semibold text-brand-600' => $progress['step_index'] === $index + 1,
                        'text-ink-500' => $progress['step_index'] !== $index + 1,
                    ])>
                        <span @class([
                            'size-4 flex-none rounded-full border-[1.5px]',
                            'border-brand-500 bg-brand-500' => $progress['step_index'] !== null && $index + 1 <= $progress['step_index'],
                            'border-ink-300' => $progress['step_index'] === null || $index + 1 > $progress['step_index'],
                        ])></span>
                        {{ $step }}
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @if ($project->status === \App\Enums\ProjectStatus::Generating)
        <script>
            // Polling: el job corre en cola, así que la vista pregunta por el estado.
            (function () {
                const watcher = document.getElementById('generation-watcher');
                const url = watcher.dataset.statusUrl;

                const poll = async () => {
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;

                    const state = await response.json();
                    if (state.redirect_to) {
                        window.location.href = state.redirect_to;
                    } else if (state.is_terminal) {
                        window.location.reload();
                    }
                };

                setInterval(poll, 2000);
            })();
        </script>
    @endif
</x-layouts.app>
