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
        Schema::create('profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('avatar_character_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->string('display_name');
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedTinyInteger('hearts')->default(5);
            $table->unsignedInteger('streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->date('last_practice_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
