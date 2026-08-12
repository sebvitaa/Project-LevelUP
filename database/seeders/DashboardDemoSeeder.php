<?php

namespace Database\Seeders;

use App\Enums\ProjectGenerationStage;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Cartera reproducible para revisar visualmente el dashboard sin consumir IA. */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoProjectSeeder::class);

        $user = User::where('email', 'demo@levelup.test')->sole();
        $user->forceFill(['ai_credits_limit' => 20, 'ai_credits_used' => 12])->save();

        $demo = Project::whereBelongsTo($user)->where('name', 'App Banca Móvil')->sole();
        $demo->activities()->orderBy('grid_column')->take(3)->update(['completed_at' => now()->subDays(2)]);

        foreach ($this->portfolio() as $item) {
            $project = Project::updateOrCreate(
                ['user_id' => $user->id, 'name' => $item['name']],
                [
                    'type' => $item['type'],
                    'prompt' => $item['prompt'],
                    'starts_on' => today()->subDays($item['started']),
                    'deadline' => today()->addDays($item['deadline']),
                    'team_size' => $item['team'],
                    'status' => ProjectStatus::Ready,
                    'generation_stage' => ProjectGenerationStage::Complete,
                    'generated_at' => now(),
                    'total_duration_days' => $item['duration'],
                    'generation_error' => null,
                ],
            );

            $project->activities()->delete();
            $activity = $project->activities()->forceCreate([
                'code' => 'A',
                'name' => $item['activity'],
                'description' => $item['prompt'],
                'duration_days' => $item['duration'],
                'early_start' => 0,
                'early_finish' => $item['duration'],
                'late_start' => 0,
                'late_finish' => $item['duration'],
                'slack' => 0,
                'is_critical' => true,
                'grid_column' => 0,
                'grid_row' => 0,
            ]);

            if ($item['completed']) {
                $activity->forceFill(['completed_at' => now()->subDays(5)])->save();
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function portfolio(): array
    {
        return [
            ['name' => 'Migración ERP Finanzas', 'type' => ProjectType::Software, 'prompt' => 'Migrar el ERP financiero sin interrumpir la operación.', 'activity' => 'Migración e integración', 'started' => 20, 'deadline' => 35, 'duration' => 42, 'team' => 8, 'completed' => false],
            ['name' => 'Rediseño Web Corporativa', 'type' => ProjectType::Marketing, 'prompt' => 'Rediseñar el sitio y relanzarlo con nueva identidad.', 'activity' => 'Diseño, contenido y publicación', 'started' => 40, 'deadline' => 2, 'duration' => 48, 'team' => 4, 'completed' => false],
            ['name' => 'Portal de Clientes v2', 'type' => ProjectType::Software, 'prompt' => 'Crear la segunda versión del portal de autoatención.', 'activity' => 'Entrega del portal', 'started' => 70, 'deadline' => -10, 'duration' => 55, 'team' => 5, 'completed' => true],
        ];
    }
}
