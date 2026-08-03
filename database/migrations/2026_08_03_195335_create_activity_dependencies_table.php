<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aristas del grafo CPM: "activity_id no puede empezar hasta que
     * predecessor_id termine" (relación fin-comienzo).
     */
    public function up(): void
    {
        Schema::create('activity_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('predecessor_id')->constrained('activities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'predecessor_id']);
            $table->index('predecessor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_dependencies');
    }
};
