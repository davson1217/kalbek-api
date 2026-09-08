<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('cefr_level')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->default(2);
            $table->string('status')->default('published');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_notes');
    }
};
