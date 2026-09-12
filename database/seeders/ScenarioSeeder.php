<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RestaurantScenarioSeeder::class,
            PharmacyVisitScenarioSeeder::class,
            A1ScenarioSeeder::class,
            SusipazinkimeUnitSeeder::class,
            MaistasIrGerimaiUnitSeeder::class,
            KurVietosIrKryptysUnitSeeder::class,
            SeimaIrZmonesUnitSeeder::class,
            EnglishShopScenarioSeeder::class,
            ContentTranslationSeeder::class,
        ]);
    }
}
