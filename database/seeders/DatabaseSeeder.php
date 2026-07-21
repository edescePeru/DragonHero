<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(WorldCatalogSeeder::class);
        $this->call(CharacterLevelRequirementSeeder::class);
        $this->call(GameHomeCardSeeder::class);
    }
}
