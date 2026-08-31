<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['scenarios', 'scenes', 'goals', 'npc_lines'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('cefr_level')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['scenarios', 'scenes', 'goals', 'npc_lines'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('cefr_level');
            });
        }
    }
};
