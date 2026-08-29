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
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle');
            $table->text('description');
            $table->string('emoji', 24);
            $table->string('tone', 32)->default('primary');
            $table->string('start_scene_slug')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
