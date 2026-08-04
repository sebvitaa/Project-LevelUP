<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use App\Services\Cpm\CpmCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Base de datos provisional del dashboard.
 *
 * Puebla la cartera que se ve en el mockup de la pantalla 02. Cada proyecto
 * tiene una malla real resuelta por CpmCalculator y actividades cerradas en
 * fechas repartidas por las últimas siete semanas, para que la miniatura de
 * tendencia dibuje una curva de verdad en vez de una línea plana.
 *
 * Reutiliza DemoProjectSeeder para «App Banca Móvil»: esa malla es la de
 * referencia del proyecto y vive en un solo lugar.
 *
 * Es data de demostración, no de producción. Se regenera con
 * `php artisan migrate:fresh --seed`.
 */
class DashboardDemoSeeder extends Seeder
{
    /** Días que abarca la miniatura de tendencia del dashboard. */
    private const TREND_SPAN_DAYS = 49;

    public function __construct(private readonly CpmCalculator $cpm) {}

    public function run(): void
    {
        $this->call(DemoProjectSeeder::class);

        $user = User::where('email', 'demo@levelup.test')->sole();

        // Consumo de cuota visible en el medidor de la barra lateral.
        $user->forceFill(['ai_credits_limit' => 20, 'ai_credits_used' => 12])->save();

        // La malla de referencia llega sin avance; el dashboard necesita uno.
        $this->markCompleted(Project::where('name', 'App Banca Móvil')->sole(), completed: 6);

        foreach ($this->portfolio() as $definition) {
            $this->createProject($user, $definition);
        }
    }

    /**
     * @param  array{
     *     name: string, type: ProjectType, prompt: string, status: ProjectStatus,
     *     starts_days_ago: int, deadline_in_days: ?int, team_size: int, completed: int,
     *     activities: array<int, array{0: string, 1: string, 2: int, 3: array<int, string>}>
     * }  $definition
     */
    private function createProject(User $user, array $definition): void
    {
        $project = Project::updateOrCreate(
            ['user_id' => $user->id, 'name' => $definition['name']],
            [
                'type' => $definition['type'],
                'prompt' => $definition['prompt'],
                'starts_on' => Carbon::today()->subDays($definition['starts_days_ago']),
                'deadline' => $definition['deadline_in_days'] === null
                    ? null
                    : Carbon::today()->addDays($definition['deadline_in_days']),
                'team_size' => $definition['team_size'],
                'status' => $definition['status'],
                'generated_at' => $definition['status'] === ProjectStatus::Ready ? now() : null,
                'generation_error' => null,
            ]
        );

        $project->activities()->delete();

        // Un borrador todavía no tiene malla: así se ve el estado inicial.
        if ($definition['activities'] === []) {
            $project->forceFill(['total_duration_days' => null])->save();

            return;
        }

        $schedule = $this->cpm->calculate(array_map(
            fn (array $activity): array => [
                'code' => $activity[0],
                'duration_days' => $activity[2],
                'predecessors' => $activity[3],
            ],
            $definition['activities']
        ));

        $models = [];

        foreach ($definition['activities'] as [$code, $name, $duration]) {
            $models[$code] = $project->activities()->forceCreate([
                'code' => $code,
                'name' => $name,
                'description' => "Actividad «{$name}» del proyecto {$definition['name']}. En la aplicación real esta descripción la redacta la IA a partir del prompt del usuario.",
                'duration_days' => $duration,
                ...$schedule[$code]->toAttributes(),
            ]);
        }

        foreach ($definition['activities'] as [$code, , , $predecessors]) {
            $models[$code]->predecessors()->sync(array_map(
                fn (string $predecessor): int => $models[$predecessor]->getKey(),
                $predecessors
            ));
        }

        $project->forceFill(['total_duration_days' => $this->cpm->totalDuration($schedule)])->save();

        $this->markCompleted($project, $definition['completed']);
    }

    /**
     * Cierra las primeras actividades del proyecto, en fechas escalonadas hacia
     * atrás, de modo que el avance haya ido subiendo semana a semana.
     */
    private function markCompleted(Project $project, int $completed): void
    {
        if ($completed < 1) {
            return;
        }

        $activities = $project->activities()->orderBy('grid_column')->orderBy('grid_row')->take($completed)->get();
        $count = $activities->count();

        // El primer cierre queda al inicio de la ventana de tendencia y el
        // último hace dos días, para que el contador "cerradas esta semana"
        // tampoco salga en cero.
        foreach ($activities as $index => $activity) {
            $daysAgo = $count === 1
                ? intdiv(self::TREND_SPAN_DAYS, 2)
                : (int) round(self::TREND_SPAN_DAYS - $index * ((self::TREND_SPAN_DAYS - 2) / ($count - 1)));

            $activity->forceFill([
                'completed_at' => now()->subDays($daysAgo)->setTime(9, 0),
            ])->save();
        }
    }

    /**
     * La cartera de demostración, sin «App Banca Móvil» (la trae
     * DemoProjectSeeder). Cada actividad es
     * `[código, nombre, duración en días, precedentes]`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function portfolio(): array
    {
        return [
            [
                'name' => 'Migración ERP Finanzas',
                'type' => ProjectType::Software,
                'prompt' => 'Migrar el ERP del área de finanzas a la nueva plataforma, sin cortar la operación durante el cierre mensual.',
                'status' => ProjectStatus::Ready,
                'starts_days_ago' => 40,
                'deadline_in_days' => 58,
                'team_size' => 8,
                'completed' => 6,
                'activities' => [
                    ['A', 'Inventario de módulos actuales', 6, []],
                    ['B', 'Mapeo de datos maestros', 9, ['A']],
                    ['C', 'Definición de arquitectura destino', 7, ['A']],
                    ['D', 'Provisionamiento de ambientes', 5, ['C']],
                    ['E', 'Desarrollo de conectores', 14, ['B', 'D']],
                    ['F', 'Migración de datos históricos', 11, ['E']],
                    ['G', 'Adaptación de reportes', 8, ['B']],
                    ['H', 'Pruebas de integración', 9, ['F', 'G']],
                    ['I', 'Pruebas de aceptación con finanzas', 7, ['H']],
                    ['J', 'Capacitación de usuarios', 6, ['I']],
                    ['K', 'Plan de contingencia y rollback', 4, ['H']],
                    ['L', 'Ensayo de corte', 3, ['I', 'K']],
                    ['M', 'Salida a producción', 2, ['J', 'L']],
                    ['N', 'Acompañamiento post salida', 10, ['M']],
                ],
            ],

            [
                'name' => 'Rediseño Web Corporativa',
                'type' => ProjectType::Marketing,
                'prompt' => 'Rediseñar el sitio corporativo con nueva identidad visual, migrar el contenido y relanzarlo con campaña de difusión.',
                'status' => ProjectStatus::Ready,
                // Ruta crítica de 53 d desde hace 46: termina en 7 días, pero
                // la fecha límite es en 3. Queda atrasado 4 días, como en el
                // mockup, y así la demo muestra el estado crítico.
                'starts_days_ago' => 46,
                'deadline_in_days' => 3,
                'team_size' => 4,
                'completed' => 3,
                'activities' => [
                    ['A', 'Auditoría del sitio actual', 4, []],
                    ['B', 'Definición de identidad visual', 8, ['A']],
                    ['C', 'Arquitectura de información', 6, ['A']],
                    ['D', 'Diseño de plantillas', 10, ['B', 'C']],
                    ['E', 'Redacción de contenidos', 12, ['C']],
                    ['F', 'Maquetación', 11, ['D']],
                    ['G', 'Migración de contenidos', 7, ['E', 'F']],
                    ['H', 'Optimización SEO', 5, ['G']],
                    ['I', 'Pruebas de accesibilidad', 4, ['F']],
                    ['J', 'Campaña de relanzamiento', 6, ['H']],
                    ['K', 'Publicación', 2, ['I', 'J']],
                ],
            ],

            [
                'name' => 'Portal de Clientes v2',
                'type' => ProjectType::Software,
                'prompt' => 'Segunda versión del portal de clientes, con autoatención, historial de pagos y descarga de documentos.',
                'status' => ProjectStatus::Ready,
                'starts_days_ago' => 90,
                'deadline_in_days' => -16,
                'team_size' => 4,
                'completed' => 9,
                'activities' => [
                    ['A', 'Análisis de la versión anterior', 4, []],
                    ['B', 'Diseño de la autoatención', 7, ['A']],
                    ['C', 'Modelo de permisos', 5, ['A']],
                    ['D', 'Historial de pagos', 9, ['C']],
                    ['E', 'Descarga de documentos', 6, ['C']],
                    ['F', 'Interfaz del portal', 10, ['B']],
                    ['G', 'Integración con facturación', 6, ['D']],
                    ['H', 'QA funcional', 5, ['E', 'F', 'G']],
                    ['I', 'Despliegue', 2, ['H']],
                ],
            ],

            [
                'name' => 'Lanzamiento Campaña Q4',
                'type' => ProjectType::Event,
                'prompt' => '',
                'status' => ProjectStatus::Draft,
                'starts_days_ago' => 0,
                'deadline_in_days' => null,
                'team_size' => 3,
                'completed' => 0,
                'activities' => [],
            ],
        ];
    }
}
