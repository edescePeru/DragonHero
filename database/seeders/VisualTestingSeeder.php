<?php
namespace Database\Seeders;use Illuminate\Database\Seeder;use Illuminate\Support\Facades\DB;use RuntimeException;
class VisualTestingSeeder extends Seeder{public function run(){if(!app()->environment('testing')||substr((string)DB::connection()->getDatabaseName(),-8)!=='_testing')throw new RuntimeException('VisualTestingSeeder requires an isolated _testing database.');$this->call([WorldCatalogSeeder::class,CharacterLevelRequirementSeeder::class,CharacterEquipmentTestingSeeder::class,ItemRefinementTestingSeeder::class,RefinementStatModifierTestingSeeder::class]);}}
