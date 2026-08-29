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
        Schema::create('speaking_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->foreignId('scene_id')->constrained()->restrictOnDelete();
            $table->foreignId('goal_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->string('audio_path')->nullable();
            $table->text('transcript')->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->text('corrected_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('graded_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'scenario_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speaking_attempts');
    }
};
