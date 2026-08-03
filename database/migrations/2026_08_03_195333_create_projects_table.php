<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type')->default('blank');
            $table->text('prompt')->nullable();

            $table->date('starts_on');
            $table->date('deadline')->nullable();
            $table->unsignedSmallInteger('team_size')->nullable();

            $table->string('status')->default('draft');
            $table->text('generation_error')->nullable();
            $table->timestamp('generated_at')->nullable();

            /** Largo de la ruta crítica en días, cacheado tras el cálculo CPM. */
            $table->unsignedSmallInteger('total_duration_days')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
