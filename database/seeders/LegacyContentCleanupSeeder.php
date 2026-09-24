<?php

namespace Database\Seeders;

use App\ContentStatus;
use App\Models\Scenario;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegacyContentCleanupSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $legacyUnitSlugs = [
        'a1-practice-library',
        'sveikata',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->archiveLegacyUnits();
            $this->archivePublishedScenariosWithoutUnits();
        });
    }

    private function archiveLegacyUnits(): void
    {
        Unit::query()
            ->whereIn('slug', $this->legacyUnitSlugs)
            ->with('scenarios')
            ->get()
            ->each(function (Unit $unit): void {
                $unit->scenarios()->update([
                    'status' => ContentStatus::Archived,
                    'published_at' => null,
                ]);

                $unit->update([
                    'status' => ContentStatus::Archived,
                    'published_at' => null,
                ]);
            });
    }

    private function archivePublishedScenariosWithoutUnits(): void
    {
        Scenario::query()
            ->whereNull('unit_id')
            ->where('status', ContentStatus::Published)
            ->update([
                'status' => ContentStatus::Archived,
                'published_at' => null,
            ]);
    }
}
