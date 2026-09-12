<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table): void {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('language_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
