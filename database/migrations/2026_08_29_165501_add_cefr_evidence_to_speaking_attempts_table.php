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
        Schema::table('speaking_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('grammar_score')->nullable()->after('score');
            $table->unsignedTinyInteger('vocabulary_score')->nullable()->after('grammar_score');
            $table->unsignedTinyInteger('cohesion_score')->nullable()->after('vocabulary_score');
            $table->unsignedTinyInteger('task_completion_score')->nullable()->after('cohesion_score');
            $table->unsignedTinyInteger('pronunciation_score')->nullable()->after('task_completion_score');
            $table->unsignedTinyInteger('overall_score')->nullable()->after('pronunciation_score');
            $table->string('attempt_cefr_level')->nullable()->after('overall_score')->index();
            $table->string('evaluation_provider')->nullable()->after('attempt_cefr_level');
            $table->string('evaluation_model')->nullable()->after('evaluation_provider');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation_model')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('speaking_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'grammar_score',
                'vocabulary_score',
                'cohesion_score',
                'task_completion_score',
                'pronunciation_score',
                'overall_score',
                'attempt_cefr_level',
                'evaluation_provider',
                'evaluation_model',
                'evaluated_at',
            ]);
        });
    }
};
