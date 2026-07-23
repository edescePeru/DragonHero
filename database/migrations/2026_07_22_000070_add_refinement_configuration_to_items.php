<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRefinementConfigurationToItems extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('allows_refinement')->default(false)->after('absorb_damage_basis_points');
            $table->string('refinement_stat', 16)->default('none')->after('allows_refinement');
        });

        DB::table('items')
            ->where('item_type', 'equipment')
            ->where('equipment_type', 'weapon')
            ->where(function ($query) {
                $query->whereNull('equipment_family')
                    ->orWhereIn('equipment_family', ['sword', 'axe', 'dagger', 'bow', 'staff', 'spear', 'wand']);
            })
            ->update(['allows_refinement' => true, 'refinement_stat' => 'attack']);

        DB::table('items')
            ->where('item_type', 'equipment')
            ->where(function ($query) {
                $query->whereIn('equipment_type', ['helmet', 'armor', 'gloves', 'boots'])
                    ->orWhere('equipment_family', 'shield');
            })
            ->update(['allows_refinement' => true, 'refinement_stat' => 'defense']);
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['allows_refinement', 'refinement_stat']);
        });
    }
}
