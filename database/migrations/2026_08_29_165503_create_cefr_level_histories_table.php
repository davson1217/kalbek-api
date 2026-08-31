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
        Schema::create('cefr_level_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_language_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language_code', 10)->default('lt');
            $table->string('previous_cefr_level')->nullable();
            $table->string('new_cefr_level');
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->unsignedInteger('evidence_attempts_count')->default(0);
            $table->text('reason');
            $table->timestamp('evaluated_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cefr_level_histories');
    }
};
