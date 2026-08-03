<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuota mensual de generaciones con IA. Es la base del plan de suscripción
     * que quedó como idea en el board del grupo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('ai_credits_limit')->default(20)->after('password');
            $table->unsignedSmallInteger('ai_credits_used')->default(0)->after('ai_credits_limit');
            $table->timestamp('ai_credits_reset_at')->nullable()->after('ai_credits_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_credits_limit', 'ai_credits_used', 'ai_credits_reset_at']);
        });
    }
};
