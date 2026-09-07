<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table): void {
            $table->string('title', 160)->nullable()->after('slug');
        });

        DB::table('scenes')
            ->orderBy('id')
            ->each(function (object $scene): void {
                DB::table('scenes')
                    ->where('id', $scene->id)
                    ->update(['title' => str((string) $scene->slug)->replace('-', ' ')->headline()->value()]);
            });

    }

    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table): void {
            $table->dropColumn('title');
        });
    }
};
