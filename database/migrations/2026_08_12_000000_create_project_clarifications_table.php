<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_clarifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('round')->default(1);
            $table->unsignedInteger('generation_attempt');
            $table->string('key', 64);
            $table->text('question');
            $table->text('rationale')->nullable();
            $table->string('input_type')->default('text');
            $table->json('options')->nullable();
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'generation_attempt', 'round', 'key']);
            $table->index(['project_id', 'generation_attempt', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_clarifications');
    }
};
