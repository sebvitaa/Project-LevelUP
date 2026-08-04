<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Base de datos provisional: la cartera completa del dashboard.
        // Incluye a DemoProjectSeeder, que aporta la malla de referencia.
        $this->call(DashboardDemoSeeder::class);
    }
}
