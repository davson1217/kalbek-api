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
        Schema::create('generated_audio', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->text('text');
            $table->string('voice')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->default('audio/mpeg');
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_audio');
    }
};
