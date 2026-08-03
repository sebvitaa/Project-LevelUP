<?php

namespace App\Services\Cpm;

use InvalidArgumentException;

/**
 * Resuelve el método de la ruta crítica (CPM) sobre un grafo de actividades.
 *
 * Trabaja con arreglos planos y no conoce Eloquent: recibe la definición del
 * grafo, hace la pasada hacia adelante (ES/EF), la pasada hacia atrás (LS/LF)
 * y devuelve holguras y ruta crítica. El persistir los resultados es tarea de
 * quien lo llama.
 *
 * Las duraciones están en días corridos y las dependencias son fin-comienzo.
 */
class CpmCalculator
{
    /**
     * @param  array<int, array{code: string, duration_days: int, predecessors: array<int, string>}>  $activities
     * @return array<string, ScheduledActivity> Indexado por código de actividad.
     *
     * @throws InvalidArgumentException Si hay códigos repetidos, precedentes
     *                                  inexistentes o un ciclo en el grafo.
     */
    public function calculate(array $activities): array
    {
        $graph = $this->buildGraph($activities);
        $order = $this->topologicalOrder($graph);

        [$earlyStart, $earlyFinish] = $this->forwardPass($graph, $order);
        $projectDuration = $earlyFinish === [] ? 0 : max($earlyFinish);
        [$lateStart, $lateFinish] = $this->backwardPass($graph, $order, $projectDuration);
        $columns = $this->columnPositions($graph, $order);
        $rows = $this->rowPositions($graph, $columns, $lateStart, $earlyStart);

        $result = [];

        foreach ($graph as $code => $node) {
            $slack = $lateStart[$code] - $earlyStart[$code];

            $result[$code] = new ScheduledActivity(
                code: $code,
                durationDays: $node['duration'],
                earlyStart: $earlyStart[$code],
                earlyFinish: $earlyFinish[$code],
                lateStart: $lateStart[$code],
                lateFinish: $lateFinish[$code],
                slack: $slack,
                isCritical: $slack === 0,
                gridColumn: $columns[$code],
                gridRow: $rows[$code],
            );
        }

        return $result;
    }

    /**
     * Duración total del proyecto: el mayor fin temprano de la malla.
     *
     * @param  array<string, ScheduledActivity>  $schedule
     */
    public function totalDuration(array $schedule): int
    {
        if ($schedule === []) {
            return 0;
        }

        return max(array_map(fn (ScheduledActivity $a): int => $a->earlyFinish, $schedule));
    }

    /**
     * Códigos de la ruta crítica, en orden cronológico.
     *
     * @param  array<string, ScheduledActivity>  $schedule
     * @return array<int, string>
     */
    public function criticalPath(array $schedule): array
    {
        $critical = array_filter($schedule, fn (ScheduledActivity $a): bool => $a->isCritical);

        usort($critical, fn (ScheduledActivity $a, ScheduledActivity $b): int => $a->earlyStart <=> $b->earlyStart);

        return array_map(fn (ScheduledActivity $a): string => $a->code, $critical);
    }

    /**
     * @param  array<int, array{code: string, duration_days: int, predecessors: array<int, string>}>  $activities
     * @return array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>
     */
    private function buildGraph(array $activities): array
    {
        $graph = [];

        foreach ($activities as $activity) {
            $code = $activity['code'];

            if (isset($graph[$code])) {
                throw new InvalidArgumentException("La actividad [{$code}] está repetida.");
            }

            if ($activity['duration_days'] < 0) {
                throw new InvalidArgumentException("La actividad [{$code}] tiene una duración negativa.");
            }

            $graph[$code] = [
                'duration' => $activity['duration_days'],
                'predecessors' => array_values(array_unique($activity['predecessors'])),
                'successors' => [],
            ];
        }

        foreach ($graph as $code => $node) {
            foreach ($node['predecessors'] as $predecessor) {
                if (! isset($graph[$predecessor])) {
                    throw new InvalidArgumentException(
                        "La actividad [{$code}] depende de [{$predecessor}], que no existe en la malla."
                    );
                }

                $graph[$predecessor]['successors'][] = $code;
            }
        }

        return $graph;
    }

    /**
     * Orden topológico por el algoritmo de Kahn. Detecta ciclos de paso.
     *
     * @param  array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>  $graph
     * @return array<int, string>
     */
    private function topologicalOrder(array $graph): array
    {
        $pending = [];

        foreach ($graph as $code => $node) {
            $pending[$code] = count($node['predecessors']);
        }

        $queue = array_keys(array_filter($pending, fn (int $count): bool => $count === 0));
        sort($queue);

        $order = [];

        while ($queue !== []) {
            $code = array_shift($queue);
            $order[] = $code;

            foreach ($graph[$code]['successors'] as $successor) {
                if (--$pending[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        if (count($order) !== count($graph)) {
            $cycle = array_keys(array_filter($pending, fn (int $count): bool => $count > 0));

            throw new InvalidArgumentException(
                'La malla tiene una dependencia circular entre: '.implode(', ', $cycle).'.'
            );
        }

        return $order;
    }

    /**
     * Pasada hacia adelante: ES = mayor EF de los precedentes, EF = ES + duración.
     *
     * @param  array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>  $graph
     * @param  array<int, string>  $order
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function forwardPass(array $graph, array $order): array
    {
        $earlyStart = [];
        $earlyFinish = [];

        foreach ($order as $code) {
            $starts = array_map(
                fn (string $predecessor): int => $earlyFinish[$predecessor],
                $graph[$code]['predecessors']
            );

            $earlyStart[$code] = $starts === [] ? 0 : max($starts);
            $earlyFinish[$code] = $earlyStart[$code] + $graph[$code]['duration'];
        }

        return [$earlyStart, $earlyFinish];
    }

    /**
     * Pasada hacia atrás: LF = menor LS de las sucesoras, LS = LF - duración.
     *
     * @param  array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>  $graph
     * @param  array<int, string>  $order
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function backwardPass(array $graph, array $order, int $projectDuration): array
    {
        $lateStart = [];
        $lateFinish = [];

        foreach (array_reverse($order) as $code) {
            $finishes = array_map(
                fn (string $successor): int => $lateStart[$successor],
                $graph[$code]['successors']
            );

            $lateFinish[$code] = $finishes === [] ? $projectDuration : min($finishes);
            $lateStart[$code] = $lateFinish[$code] - $graph[$code]['duration'];
        }

        return [$lateStart, $lateFinish];
    }

    /**
     * Columna de cada actividad en el lienzo: su profundidad en el grafo.
     *
     * @param  array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>  $graph
     * @param  array<int, string>  $order
     * @return array<string, int>
     */
    private function columnPositions(array $graph, array $order): array
    {
        $columns = [];

        foreach ($order as $code) {
            $depths = array_map(
                fn (string $predecessor): int => $columns[$predecessor] + 1,
                $graph[$code]['predecessors']
            );

            $columns[$code] = $depths === [] ? 0 : max($depths);
        }

        return $columns;
    }

    /**
     * Fila dentro de cada columna. Las actividades críticas van arriba, para
     * que la ruta crítica se lea como una línea horizontal en la malla.
     *
     * @param  array<string, array{duration: int, predecessors: array<int, string>, successors: array<int, string>}>  $graph
     * @param  array<string, int>  $columns
     * @param  array<string, int>  $lateStart
     * @param  array<string, int>  $earlyStart
     * @return array<string, int>
     */
    private function rowPositions(array $graph, array $columns, array $lateStart, array $earlyStart): array
    {
        $byColumn = [];

        foreach (array_keys($graph) as $code) {
            $byColumn[$columns[$code]][] = $code;
        }

        $rows = [];

        foreach ($byColumn as $codes) {
            usort($codes, function (string $a, string $b) use ($lateStart, $earlyStart): int {
                $slackA = $lateStart[$a] - $earlyStart[$a];
                $slackB = $lateStart[$b] - $earlyStart[$b];

                return [$slackA, $a] <=> [$slackB, $b];
            });

            foreach ($codes as $row => $code) {
                $rows[$code] = $row;
            }
        }

        return $rows;
    }
}
