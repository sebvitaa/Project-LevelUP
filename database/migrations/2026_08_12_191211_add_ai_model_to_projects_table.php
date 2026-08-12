<?php

use App\Enums\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modelo de IA con el que se generó la malla.
     *
     * Se guarda en el proyecto y no se lee del usuario al momento de generar,
     * porque la generación es asíncrona: entre el POST y el job el plan puede
     * haber vencido, y un proyecto tiene que terminar de generarse con el mismo
     * modelo con el que empezó. También deja registro de con qué se hizo cada
     * malla cuando se comparan resultados.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('ai_model')->default(AiModel::Standard->value)->after('team_size');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ai_model');
        });
    }
};
