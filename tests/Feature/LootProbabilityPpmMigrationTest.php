<?php

namespace Tests\Feature;

use AddDropProbabilityPpmToMonsterLootEntries;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LootProbabilityPpmMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_is_unsigned_int_not_null_and_seeded_values_are_exact()
    {
        $this->seed(WorldCatalogSeeder::class);

        $column = DB::selectOne(
            "SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS "
            ."WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='monster_loot_entries' "
            ."AND COLUMN_NAME='drop_probability_ppm'"
        );

        $this->assertSame('int', $column->DATA_TYPE);
        $this->assertStringContainsString('unsigned', $column->COLUMN_TYPE);
        $this->assertSame('NO', $column->IS_NULLABLE);
        $this->assertSame(700000, (int) DB::table('monster_loot_entries')->where('drop_probability_ppm', 700000)->value('drop_probability_ppm'));
    }

    public function test_legacy_backfill_is_exact_and_preserves_rows_and_relations()
    {
        $this->seed(WorldCatalogSeeder::class);
        $before = DB::table('monster_loot_entries')->orderBy('id')->get(['id', 'monster_id', 'item_id', 'minimum_quantity', 'maximum_quantity', 'status']);
        $migration = new AddDropProbabilityPpmToMonsterLootEntries();

        $migration->down();
        foreach ([10000, 7000, 1, 0] as $index => $basisPoints) {
            DB::table('monster_loot_entries')->where('id', $before[$index]->id)->update(['drop_chance_basis_points' => $basisPoints]);
        }
        $migration->up();

        $after = DB::table('monster_loot_entries')->orderBy('id')->get(['id', 'monster_id', 'item_id', 'minimum_quantity', 'maximum_quantity', 'status']);
        $this->assertEquals($before, $after);
        foreach ([1000000, 700000, 100, 0] as $index => $ppm) {
            $this->assertSame($ppm, (int) DB::table('monster_loot_entries')->where('id', $before[$index]->id)->value('drop_probability_ppm'));
        }
    }

    public function test_down_refuses_to_discard_fine_ppm_precision()
    {
        $this->seed(WorldCatalogSeeder::class);
        DB::table('monster_loot_entries')->limit(1)->update(['drop_probability_ppm' => 1]);
        $migration = new AddDropProbabilityPpmToMonsterLootEntries();

        try {
            $migration->down();
            $this->fail('Expected rollback to reject a fine PPM value.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('without losing precision', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('monster_loot_entries', 'drop_probability_ppm'));
        $this->assertSame(1, (int) DB::table('monster_loot_entries')->value('drop_probability_ppm'));
    }
}
