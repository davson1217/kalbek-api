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
            $table->renameColumn('lt', 'target_text');
            $table->renameColumn('en', 'support_translation');
        });

        Schema::table('scene_props', function (Blueprint $table) {
            $table->renameColumn('lt', 'target_text');
            $table->renameColumn('en', 'support_translation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scene_props', function (Blueprint $table) {
            $table->renameColumn('target_text', 'lt');
            $table->renameColumn('support_translation', 'en');
        });

        Schema::table('npc_lines', function (Blueprint $table) {
            $table->renameColumn('target_text', 'lt');
            $table->renameColumn('support_translation', 'en');
        });
    }
};
