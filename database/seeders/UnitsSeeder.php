<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SusipazinkimeUnitSeeder::class,
            MaistasIrGerimaiUnitSeeder::class,
            KurVietosIrKryptysUnitSeeder::class,
            SeimaIrZmonesUnitSeeder::class,
            SkaiciaiLaikasDatosUnitSeeder::class,
            ParduotuvejeUnitSeeder::class,
            ManoDienaUnitSeeder::class,
            NamaiDaiktaiUnitSeeder::class,
            TransportasKelioneMiesteUnitSeeder::class,
            OrasDrabuziaiUnitSeeder::class,
            SveikataVaistineUnitSeeder::class,
            KlasejeMokantisUnitSeeder::class,
            LaisvalaikisPomegiaiUnitSeeder::class,
            PlanaiKvietimaiUnitSeeder::class,
            GebejimaiPoreikiaiUnitSeeder::class,
            KelioneApgyvendinimasUnitSeeder::class,
            A1ReviewCapstoneUnitSeeder::class,
            EnglishShopScenarioSeeder::class,
            ContentTranslationSeeder::class,
            LegacyContentCleanupSeeder::class,
        ]);
    }
}
