{{-- Plan de suscripción — contratación simulada, sin pasarela de pago --}}
<x-layouts.app title="Tu plan">
    <x-slot:topbar>
        <span class="text-sm text-ink-500">
            <a href="{{ route('dashboard') }}" class="hover:text-ink-900">Mis proyectos</a>
            <span class="mx-1.5 text-ink-300">›</span>
            <span class="font-semibold text-ink-900">Tu plan</span>
        </span>
    </x-slot:topbar>

    <div class="mx-auto flex max-w-4xl flex-col gap-7 p-9">
        <div>
            <h1 class="text-[27px] font-semibold leading-tight tracking-tight">Tu plan</h1>
            <p class="mt-2 text-sm text-ink-500">
                Estás en el plan <span class="font-semibold text-ink-900">{{ $currentPlan->label() }}</span>.
                Te quedan <span class="num font-semibold text-ink-900">{{ $remainingCredits }}</span>
                de {{ $creditLimit }} generaciones.
                @if ($planExpiresAt !== null)
                    Se renueva el {{ $planExpiresAt->format('d-m-Y') }}.
                @endif
            </p>
        </div>

        @if (session('status'))
            <p class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700">
                {{ session('status') }}
            </p>
        @endif

        <p class="rounded-lg border border-ink-200 bg-ink-50 px-4 py-3 text-xs leading-relaxed text-ink-500">
            <span class="font-semibold text-ink-700">Demostración académica.</span>
            No hay pasarela de pago conectada: contratar el plan no genera ningún cobro y no se piden
            datos de tarjeta. Sirve para probar el flujo completo y el cambio de modelo.
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($plans as $plan)
                @php($isCurrent = $currentPlan === $plan)
                <div @class([
                    'flex flex-col gap-3 rounded-xl border-[1.5px] p-5',
                    'border-brand-500 bg-brand-50' => $isCurrent,
                    'border-ink-200' => ! $isCurrent,
                ])>
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold tracking-tight">{{ $plan->label() }}</h2>
                        @if ($isCurrent)
                            <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-[11px] font-semibold text-brand-600">
                                Tu plan
                            </span>
                        @endif
                    </div>

                    <p class="num text-[22px] font-semibold leading-none tracking-tight">
                        US${{ $plan->monthlyPriceUsd() }}
                        <span class="text-xs font-normal text-ink-500">/ mes</span>
                    </p>

                    <p class="text-xs leading-relaxed text-ink-500">{{ $plan->description() }}</p>

                    <ul class="flex flex-col gap-1.5 text-xs text-ink-700">
                        @foreach ($plan->highlights() as $highlight)
                            <li class="flex items-start gap-2">
                                <span aria-hidden="true" class="mt-0.5 text-brand-500">✓</span>
                                <span>{{ $highlight }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-auto pt-2">
                        @if ($plan === \App\Enums\SubscriptionPlan::Pro)
                            <form method="POST" action="{{ route('account.plan.store') }}">
                                @csrf
                                <button type="submit"
                                        class="h-11 w-full rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white hover:bg-brand-600">
                                    {{ $isCurrent ? 'Renovar por 30 días más' : 'Contratar por US$10' }}
                                </button>
                            </form>
                        @elseif ($currentPlan === \App\Enums\SubscriptionPlan::Pro)
                            <form method="POST" action="{{ route('account.plan.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="h-11 w-full rounded-lg border border-ink-300 px-5 text-sm font-semibold text-ink-700 hover:bg-ink-50">
                                    Volver al plan gratis
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-ink-500">Es el plan que tienes ahora.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-ink-200 pt-4">
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-ink-500 hover:text-ink-900">
                ← Volver al dashboard
            </a>
        </div>
    </div>
</x-layouts.app>
