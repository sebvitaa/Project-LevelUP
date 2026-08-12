<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Services\Cpm\CpmCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Edición de actividades desde el panel lateral de la pantalla 06.
 */
class ActivityController extends Controller
{
    /**
     * Cambiar la duración altera la ruta crítica, así que el CPM se recalcula
     * inmediatamente después de guardar.
     */
    public function update(UpdateActivityRequest $request, Activity $activity, CpmCalculator $cpm): RedirectResponse
    {
        DB::transaction(function () use ($activity, $request, $cpm): void {
            $activity->update($request->validated());
            $this->recalculate($activity, $cpm);
        });

        return redirect()->route('projects.show', [
            'project' => $activity->loadMissing('project')->project,
            'activity' => $activity->code,
            'view' => in_array($request->query('view'), ['network', 'gantt'], true) ? $request->query('view') : 'network',
        ])->with('status', 'Actividad actualizada.');
    }

    /**
     * Marca o desmarca la actividad como completada. No toca el CPM: el avance
     * es independiente de la planificación.
     */
    public function toggleCompletion(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity->loadMissing('project')->project);

        $activity->update(['completed_at' => $activity->isCompleted() ? null : now()]);

        return redirect()->route('projects.show', [
            'project' => $activity->project,
            'activity' => $activity->code,
            'view' => in_array($request->query('view'), ['network', 'gantt'], true) ? $request->query('view') : 'network',
        ]);
    }

    /**
     * Vuelve a resolver la malla completa del proyecto y guarda los tiempos.
     */
    private function recalculate(Activity $activity, CpmCalculator $cpm): void
    {
        $project = $activity->loadMissing('project')->project;
        $activities = $project->activities()->with('predecessors:id,code')->get();

        $schedule = $cpm->calculate($activities->map(fn (Activity $item): array => [
            'code' => $item->code,
            'duration_days' => $item->duration_days,
            'predecessors' => $item->predecessors->pluck('code')->all(),
        ])->all());

        foreach ($activities as $item) {
            $item->forceFill($schedule[$item->code]->toAttributes())->save();
        }

        $project->forceFill(['total_duration_days' => $cpm->totalDuration($schedule)])->save();
    }
}
