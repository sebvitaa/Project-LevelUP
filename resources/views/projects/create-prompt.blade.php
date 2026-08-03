{{-- Pantalla 04 — Descripción del proyecto (prompt) --}}
<x-layouts.app title="Nuevo proyecto · Descripción">
    <x-slot:topbar>
        <span class="text-sm text-ink-500">
            <a href="{{ route('dashboard') }}" class="hover:text-ink-900">Mis proyectos</a>
            <span class="mx-1.5 text-ink-300">›</span>
            <span class="font-semibold text-ink-900">Nuevo proyecto</span>
        </span>
        <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-[11px] font-semibold text-brand-600">
            {{ $type->label() }}
        </span>
        <div class="flex-1"></div>
        @include('projects.partials.stepper', ['current' => 2])
        <div class="flex-1"></div>
    </x-slot:topbar>

    <form method="POST" action="{{ route('projects.store') }}" class="mx-auto flex max-w-4xl flex-col gap-7 p-9">
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">

        <div>
            <h1 class="text-[27px] font-semibold leading-tight tracking-tight">Cuéntanos qué hay que hacer</h1>
            <p class="mt-2 text-sm text-ink-500">
                Escríbelo como se lo explicarías a tu equipo. Mientras más contexto des sobre entregables y
                restricciones, mejor queda la malla.
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-xs font-semibold text-ink-700">Nombre del proyecto</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120"
                   class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
            @error('name')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="prompt" class="text-xs font-semibold text-ink-700">Descripción del proyecto</label>
            <textarea id="prompt" name="prompt" rows="7" required minlength="40" maxlength="4000"
                      placeholder="Necesito lanzar la app móvil de banca personal para iOS y Android. Parte con el levantamiento de requerimientos…"
                      class="rounded-xl border-[1.5px] border-ink-300 p-4 text-sm leading-relaxed focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">{{ old('prompt') }}</textarea>
            @error('prompt')
                <p class="text-xs text-critical">{{ $message }}</p>
            @enderror
            <p class="num text-[11px] text-ink-500">
                Consulta {{ $creditLimit - $remainingCredits + 1 }} de {{ $creditLimit }}
            </p>
        </div>

        <div class="flex flex-col gap-2.5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Contexto para el cálculo</span>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex flex-col gap-1.5">
                    <label for="starts_on" class="text-xs font-semibold text-ink-700">Fecha de inicio</label>
                    <input id="starts_on" name="starts_on" type="date" required
                           value="{{ old('starts_on', now()->toDateString()) }}"
                           class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
                    @error('starts_on')
                        <p class="text-xs text-critical">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="deadline" class="text-xs font-semibold text-ink-700">Fecha límite deseada</label>
                    <input id="deadline" name="deadline" type="date" value="{{ old('deadline') }}"
                           class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
                    @error('deadline')
                        <p class="text-xs text-critical">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="team_size" class="text-xs font-semibold text-ink-700">Tamaño del equipo</label>
                    <input id="team_size" name="team_size" type="number" min="1" max="500" value="{{ old('team_size') }}"
                           class="h-10 rounded-lg border border-ink-300 px-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-3 focus:ring-brand-100">
                    @error('team_size')
                        <p class="text-xs text-critical">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-ink-200 pt-4">
            <a href="{{ route('projects.create.type') }}" class="text-sm font-semibold text-ink-500 hover:text-ink-900">
                ← Cambiar tipo
            </a>
            <div class="flex items-center gap-3">
                <span class="text-xs text-ink-500">Toma unos 30 segundos</span>
                <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">
                    Generar cronograma CPM
                </button>
            </div>
        </div>
    </form>
</x-layouts.app>
