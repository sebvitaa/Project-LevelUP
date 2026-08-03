<?php

namespace App\Http\Controllers;

use App\Enums\ProjectType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pantallas 03 y 04 — los dos pasos previos a generar la malla.
 *
 * El tipo elegido en el paso 1 viaja en sesión hasta que el paso 2 hace el
 * POST, así no quedan proyectos vacíos en la base si el usuario abandona.
 */
class ProjectWizardController extends Controller
{
    private const SESSION_KEY = 'project_wizard.type';

    /** Pantalla 03 — selección del tipo de proyecto. */
    public function type(): View
    {
        return view('projects.create-type', [
            'types' => ProjectType::cases(),
            'selected' => session(self::SESSION_KEY, ProjectType::Software->value),
        ]);
    }

    /** Guarda el tipo elegido y avanza al paso 2. */
    public function storeType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(ProjectType::class)],
        ]);

        $request->session()->put(self::SESSION_KEY, $validated['type']);

        return redirect()->route('projects.create.prompt');
    }

    /** Pantalla 04 — descripción del proyecto (prompt). */
    public function prompt(Request $request): View|RedirectResponse
    {
        $type = ProjectType::tryFrom((string) session(self::SESSION_KEY));

        if ($type === null) {
            return redirect()->route('projects.create.type');
        }

        return view('projects.create-prompt', [
            'type' => $type,
            'remainingCredits' => $request->user()->remainingAiCredits(),
            'creditLimit' => $request->user()->ai_credits_limit,
        ]);
    }
}
