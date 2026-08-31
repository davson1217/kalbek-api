<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $languageId = DB::table('languages')->updateOrInsert(
            ['code' => 'lt'],
            [
                'name' => 'Lithuanian',
                'native_name' => 'Lietuvių',
                'status' => 'active',
                'sort_order' => 10,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $languageId = DB::table('languages')->where('code', 'lt')->value('id');

        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('language_id')
                ->nullable()
                ->after('id')
                ->constrained('languages')
                ->nullOnDelete();
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->foreignId('language_id')
                ->nullable()
                ->after('id')
                ->constrained('languages')
                ->nullOnDelete();
        });

        DB::table('characters')->whereNull('language_id')->update(['language_id' => $languageId]);
        DB::table('scenarios')->whereNull('language_id')->update(['language_id' => $languageId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('language_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('language_id');
        });
    }
};
