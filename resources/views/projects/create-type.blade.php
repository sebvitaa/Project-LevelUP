{{-- Pantalla 03 — Selección del tipo de proyecto --}}
<x-layouts.app title="Nuevo proyecto · Tipo">
    <x-slot:topbar>
        <span class="text-sm text-ink-500">
            <a href="{{ route('dashboard') }}" class="hover:text-ink-900">Mis proyectos</a>
            <span class="mx-1.5 text-ink-300">›</span>
            <span class="font-semibold text-ink-900">Nuevo proyecto</span>
        </span>
        <div class="flex-1"></div>
        @include('projects.partials.stepper', ['current' => 1])
        <div class="flex-1"></div>
    </x-slot:topbar>

    <form method="POST" action="{{ route('projects.store.type') }}" class="mx-auto flex max-w-4xl flex-col gap-7 p-9">
        @csrf

        <div>
            <h1 class="text-[27px] font-semibold leading-tight tracking-tight">
                ¿Qué tipo de proyecto vas a planificar?
            </h1>
            <p class="mt-2 text-sm text-ink-500">
                Ajustamos el vocabulario, las actividades sugeridas y las unidades de tiempo según lo que elijas.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($types as $type)
                <label class="group cursor-pointer">
                    <input type="radio" name="type" value="{{ $type->value }}" class="peer sr-only"
                           @checked($selected === $type->value)>
                    <div class="flex h-full flex-col gap-2 rounded-xl border-[1.5px] border-ink-200 p-4
                                peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-3 peer-checked:ring-brand-100
                                peer-focus-visible:ring-3 peer-focus-visible:ring-brand-100">
                        <h2 class="text-sm font-semibold tracking-tight">{{ $type->label() }}</h2>
                        <p class="text-xs leading-relaxed text-ink-500">{{ $type->description() }}</p>
                        <p class="num mt-auto text-[11px] text-ink-500">
                            ≈ {{ $type->activityRange()[0] }}–{{ $type->activityRange()[1] }} actividades
                        </p>
                    </div>
                </label>
            @endforeach
        </div>

        @error('type')
            <p class="text-xs text-critical">{{ $message }}</p>
        @enderror

        <div class="flex items-center justify-between border-t border-ink-200 pt-4">
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-ink-500 hover:text-ink-900">
                ← Volver al dashboard
            </a>
            <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">
                Continuar →
            </button>
        </div>
    </form>
</x-layouts.app>
