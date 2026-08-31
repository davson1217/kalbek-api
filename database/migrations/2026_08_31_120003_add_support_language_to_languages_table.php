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
        Schema::table('languages', function (Blueprint $table) {
            $table->string('support_language_code', 10)->default('en')->after('native_name');
            $table->string('support_language_name')->default('English')->after('support_language_code');
        });

        DB::table('languages')->whereNull('support_language_code')->update([
            'support_language_code' => 'en',
            'support_language_name' => 'English',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn(['support_language_code', 'support_language_name']);
        });
    }
};
