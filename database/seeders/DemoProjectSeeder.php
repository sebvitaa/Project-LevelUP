<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use App\Services\Cpm\CpmCalculator;
use Illuminate\Database\Seeder;

/**
 * Proyecto de demostración con la misma malla que aparece en los mockups.
 *
 * Sirve para levantar la app sin gastar consultas de Gemini y para verificar
 * a ojo que el cálculo CPM coincide con lo diseñado: ruta crítica
 * A → C → D → F → G → H de 39 días, con holgura 6 en B y E.
 */
class DemoProjectSeeder extends Seeder
{
    public function run(CpmCalculator $cpm): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@levelup.test'],
            ['name' => 'Benjamín Soto', 'password' => 'password']
        );

        $project = Project::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'App Banca Móvil'],
            [
                'type' => ProjectType::Software,
                'prompt' => 'Lanzar la app móvil de banca personal para iOS y Android, desde el levantamiento de requerimientos hasta la publicación en las tiendas.',
                'starts_on' => now()->startOfMonth(),
                'deadline' => now()->startOfMonth()->addDays(45),
                'team_size' => 5,
                'status' => ProjectStatus::Ready,
                'generated_at' => now(),
            ]
        );

        $definitions = $this->activityDefinitions();

        $schedule = $cpm->calculate(array_map(fn (array $activity): array => [
            'code' => $activity['code'],
            'duration_days' => $activity['duration_days'],
            'predecessors' => $activity['predecessors'],
        ], $definitions));

        $project->activities()->delete();

        $models = [];

        foreach ($definitions as $definition) {
            $models[$definition['code']] = $project->activities()->forceCreate([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'duration_days' => $definition['duration_days'],
                ...$schedule[$definition['code']]->toAttributes(),
            ]);
        }

        foreach ($definitions as $definition) {
            $models[$definition['code']]->predecessors()->sync(array_map(
                fn (string $code): int => $models[$code]->getKey(),
                $definition['predecessors']
            ));
        }

        $project->forceFill(['total_duration_days' => $cpm->totalDuration($schedule)])->save();
    }

    /**
     * @return array<int, array{code: string, name: string, description: string, duration_days: int, predecessors: array<int, string>}>
     */
    private function activityDefinitions(): array
    {
        return [
            [
                'code' => 'A',
                'name' => 'Levantamiento de requerimientos',
                'description' => 'Reunir con el área de negocio las funcionalidades que entran al alcance y dejarlas priorizadas y firmadas.',
                'duration_days' => 5,
                'predecessors' => [],
            ],
            [
                'code' => 'B',
                'name' => 'Diseño UX/UI',
                'description' => 'Definir los flujos de la app y entregar las pantallas finales en alta fidelidad, con estados de error y carga.',
                'duration_days' => 8,
                'predecessors' => ['A'],
            ],
            [
                'code' => 'C',
                'name' => 'Arquitectura backend',
                'description' => 'Decidir la estructura de servicios, el modelo de datos y la estrategia de autenticación antes de escribir código.',
                'duration_days' => 6,
                'predecessors' => ['A'],
            ],
            [
                'code' => 'D',
                'name' => 'Desarrollo de la API',
                'description' => 'Construir los endpoints de cuentas, saldos, transferencias y notificaciones sobre la arquitectura definida en C.',
                'duration_days' => 12,
                'predecessors' => ['C'],
            ],
            [
                'code' => 'E',
                'name' => 'Desarrollo frontend',
                'description' => 'Implementar las pantallas de la app móvil contra los diseños de B, con datos simulados hasta que la API esté lista.',
                'duration_days' => 10,
                'predecessors' => ['B'],
            ],
            [
                'code' => 'F',
                'name' => 'Integración pasarela de pagos',
                'description' => 'Conectar el proveedor de pagos, manejar los estados de transacción y dejar el ambiente de pruebas certificado.',
                'duration_days' => 6,
                'predecessors' => ['D'],
            ],
            [
                'code' => 'G',
                'name' => 'QA y pruebas de carga',
                'description' => 'Ejecutar el plan de pruebas funcionales sobre frontend y pasarela integrados, más pruebas de carga del backend.',
                'duration_days' => 7,
                'predecessors' => ['E', 'F'],
            ],
            [
                'code' => 'H',
                'name' => 'Publicación en stores',
                'description' => 'Preparar fichas, capturas y builds firmadas, y acompañar la revisión de App Store y Google Play.',
                'duration_days' => 3,
                'predecessors' => ['G'],
            ],
        ];
    }
}
