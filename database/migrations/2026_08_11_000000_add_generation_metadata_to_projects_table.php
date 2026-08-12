<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('generation_stage')->nullable()->after('status');
            $table->unsignedInteger('generation_attempt')->default(0)->after('generation_stage');
            $table->unsignedInteger('charged_generation_attempt')->nullable()->after('generation_attempt');
            $table->timestamp('generation_started_at')->nullable()->after('generated_at');
            $table->timestamp('generation_progressed_at')->nullable()->after('generation_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'generation_stage',
                'generation_attempt',
                'charged_generation_attempt',
                'generation_started_at',
                'generation_progressed_at',
            ]);
        });
    }
};
