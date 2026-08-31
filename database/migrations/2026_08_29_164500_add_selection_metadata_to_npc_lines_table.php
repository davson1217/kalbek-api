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
        Schema::table('npc_lines', function (Blueprint $table) {
            $table->foreignId('trigger_goal_id')->nullable()->after('scene_id')->constrained('goals')->nullOnDelete();
            $table->unsignedInteger('priority')->default(0)->after('en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npc_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trigger_goal_id');
            $table->dropColumn('priority');
        });
    }
};
