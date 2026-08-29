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
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->unsignedInteger('version');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('system_message');
            $table->json('parameters')->nullable();
            $table->boolean('active')->default(false)->index();
            $table->timestamps();

            $table->unique(['purpose', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
