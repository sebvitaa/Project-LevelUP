<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contratación del plan Pro.
 *
 * IMPORTANTE: el cobro está simulado. No hay pasarela de pago conectada, no se
 * piden ni se guardan datos de tarjeta y contratar no le cuesta dinero a nadie.
 * Lo que sí está armado de verdad es la lógica alrededor: qué habilita el plan,
 * cuánto dura, cómo se extiende y qué pasa cuando vence. Enchufar una pasarela
 * real significa reemplazar `store()` por el retorno del proveedor de pago y
 * llamar a `activateProPlan()` recién cuando el pago venga confirmado.
 */
class SubscriptionController extends Controller
{
    /** Pantalla de plan: comparación y estado de la contratación vigente. */
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.plan', [
            'plans' => SubscriptionPlan::cases(),
            'currentPlan' => $user->currentPlan(),
            'planExpiresAt' => $user->isOnProPlan() ? $user->plan_expires_at : null,
            'remainingCredits' => $user->remainingAiCredits(),
            'creditLimit' => $user->ai_credits_limit,
        ]);
    }

    /** Activa el plan Pro. Acá iría la confirmación de la pasarela de pago. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $wasAlreadyPro = $user->isOnProPlan();

        $user->activateProPlan();

        return redirect()
            ->route('account.plan')
            ->with('status', $wasAlreadyPro
                ? 'Renovamos tu plan Pro por '.SubscriptionPlan::PRO_PERIOD_DAYS.' días más.'
                : 'Listo, ya tienes el plan Pro. Ahora puedes generar con el modelo avanzado.');
    }

    /** Vuelve al plan gratuito. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->cancelProPlan();

        return redirect()
            ->route('account.plan')
            ->with('status', 'Volviste al plan gratis. El modelo avanzado queda bloqueado.');
    }
}
