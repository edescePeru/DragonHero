<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateItemRaritiesAndAllowedRarities extends Migration
{
    public function up()
    {
        Schema::create('item_rarities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 64);
            $table->string('status', 20);
            $table->unsignedSmallInteger('sort_order');
            $table->string('visual_style', 32);
            $table->unsignedInteger('weapon_accuracy_bonus_basis_points')->default(0);
            $table->unsignedInteger('weapon_critical_bonus_basis_points')->default(0);
            $table->unsignedInteger('armor_evasion_bonus_basis_points')->default(0);
            $table->unsignedInteger('armor_speed_bonus_hundredths')->default(0);
            $table->unsignedInteger('armor_absorb_damage_bonus_basis_points')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('item_rarities')->insert([
            $this->row('common', 'Común', 10, 'neutral', 0, 0, 0, 0, 0, $now),
            $this->row('rare', 'Raro', 20, 'blue', 500, 0, 200, 0, 0, $now),
            $this->row('mythic', 'Mítico', 30, 'purple', 0, 400, 300, 100, 0, $now),
            $this->row('legendary', 'Legendario', 40, 'gold', 500, 400, 400, 200, 100, $now),
        ]);

        Schema::create('item_allowed_rarities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('item_rarity_id');
            $table->primary(['item_id', 'item_rarity_id']);
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('item_rarity_id')->references('id')->on('item_rarities')->onDelete('restrict');
        });

        $commonId = DB::table('item_rarities')->where('code', 'common')->value('id');
        DB::table('items')->orderBy('id')->select('id')->chunk(500, function ($items) use ($commonId) {
            $rows = [];
            foreach ($items as $item) {
                $rows[] = ['item_id' => $item->id, 'item_rarity_id' => $commonId];
            }
            if (!empty($rows)) {
                DB::table('item_allowed_rarities')->insert($rows);
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('item_allowed_rarities');
        Schema::dropIfExists('item_rarities');
    }

    private function row($code, $name, $order, $style, $accuracy, $critical, $evasion, $speed, $absorb, $now)
    {
        return [
            'code' => $code,
            'name' => $name,
            'status' => 'active',
            'sort_order' => $order,
            'visual_style' => $style,
            'weapon_accuracy_bonus_basis_points' => $accuracy,
            'weapon_critical_bonus_basis_points' => $critical,
            'armor_evasion_bonus_basis_points' => $evasion,
            'armor_speed_bonus_hundredths' => $speed,
            'armor_absorb_damage_bonus_basis_points' => $absorb,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
