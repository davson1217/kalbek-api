<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table): void {
            $table->boolean('is_free')->default(false)->after('status')->index();
        });

        $limit = (int) config('subscriptions.free_scenario_limit', 2);

        DB::table('scenarios')
            ->select('language_id')
            ->whereNotNull('language_id')
            ->distinct()
            ->orderBy('language_id')
            ->get()
            ->each(function ($language) use ($limit): void {
                DB::table('scenarios')
                    ->where('language_id', $language->language_id)
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->limit($limit)
                    ->update(['is_free' => true]);
            });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table): void {
            $table->dropColumn('is_free');
        });
    }
};
