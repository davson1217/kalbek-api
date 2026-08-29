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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('next_scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->string('slug');
            $table->string('label');
            $table->text('intent');
            $table->text('example');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scene_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
