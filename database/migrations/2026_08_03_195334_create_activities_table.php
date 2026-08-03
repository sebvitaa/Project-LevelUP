<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            /** Código corto que identifica la actividad en la malla: A, B, C… */
            $table->string('code', 4);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days');

            /** Resultados del cálculo CPM. Null mientras la malla no se ha resuelto. */
            $table->unsignedSmallInteger('early_start')->nullable();
            $table->unsignedSmallInteger('early_finish')->nullable();
            $table->unsignedSmallInteger('late_start')->nullable();
            $table->unsignedSmallInteger('late_finish')->nullable();
            $table->unsignedSmallInteger('slack')->nullable();
            $table->boolean('is_critical')->default(false);

            /** Posición en el lienzo de la malla, asignada al ordenar el grafo. */
            $table->unsignedSmallInteger('grid_column')->default(0);
            $table->unsignedSmallInteger('grid_row')->default(0);

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'is_critical']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
