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
        Schema::create('learner_language_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 10)->default('lt');
            $table->string('current_cefr_level')->default('pre_a1')->index();
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->unsignedTinyInteger('grammar_score')->nullable();
            $table->unsignedTinyInteger('vocabulary_score')->nullable();
            $table->unsignedTinyInteger('cohesion_score')->nullable();
            $table->unsignedTinyInteger('task_completion_score')->nullable();
            $table->unsignedTinyInteger('pronunciation_score')->nullable();
            $table->unsignedInteger('evidence_attempts_count')->default(0);
            $table->timestamp('evidence_window_started_at')->nullable();
            $table->timestamp('evidence_window_ended_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learner_language_levels');
    }
};
