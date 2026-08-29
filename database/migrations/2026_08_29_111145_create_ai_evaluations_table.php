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
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('speaking_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->json('input');
            $table->json('output')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('status')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
