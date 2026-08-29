<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RestaurantScenarioSeeder::class);
    }
}
