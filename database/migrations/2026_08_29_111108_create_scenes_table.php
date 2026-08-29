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
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->text('setting');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scenario_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
