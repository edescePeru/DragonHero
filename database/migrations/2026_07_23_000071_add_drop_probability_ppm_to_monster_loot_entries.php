<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDropProbabilityPpmToMonsterLootEntries extends Migration
{
    public function up()
    {
        Schema::table('monster_loot_entries', function (Blueprint $table) {
            $table->unsignedInteger('drop_probability_ppm')->nullable()->after('drop_chance_basis_points');
        });

        DB::table('monster_loot_entries')->update([
            'drop_probability_ppm' => DB::raw('drop_chance_basis_points * 100'),
        ]);

        $invalid = DB::table('monster_loot_entries')
            ->where(function ($query) {
                $query->whereNull('drop_probability_ppm')
                    ->orWhere('drop_probability_ppm', '<', 0)
                    ->orWhere('drop_probability_ppm', '>', 1000000)
                    ->orWhereRaw('drop_probability_ppm <> drop_chance_basis_points * 100');
            })
            ->exists();

        if ($invalid) {
            throw new \RuntimeException('Loot probability BP to PPM backfill verification failed.');
        }

        DB::statement(
            'ALTER TABLE monster_loot_entries '
            .'MODIFY drop_chance_basis_points SMALLINT UNSIGNED NULL, '
            .'MODIFY drop_probability_ppm INT UNSIGNED NOT NULL'
        );
    }

    public function down()
    {
        if (!Schema::hasColumn('monster_loot_entries', 'drop_probability_ppm')) {
            return;
        }

        if (DB::table('monster_loot_entries')->whereRaw('MOD(drop_probability_ppm, 100) <> 0')->exists()) {
            throw new \RuntimeException('Cannot roll back fine PPM loot probabilities without losing precision.');
        }

        DB::table('monster_loot_entries')->update([
            'drop_chance_basis_points' => DB::raw('drop_probability_ppm / 100'),
        ]);

        DB::statement(
            'ALTER TABLE monster_loot_entries '
            .'MODIFY drop_chance_basis_points SMALLINT UNSIGNED NOT NULL'
        );

        Schema::table('monster_loot_entries', function (Blueprint $table) {
            $table->dropColumn('drop_probability_ppm');
        });
    }
}
