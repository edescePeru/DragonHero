<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class AddAbsorbDamageBasisPointsToItemsTable extends Migration{public function up(){Schema::table('items',function(Blueprint $table){$table->unsignedInteger('absorb_damage_basis_points')->default(0)->after('attack_speed_bonus');});}public function down(){Schema::table('items',function(Blueprint $table){$table->dropColumn('absorb_damage_basis_points');});}}
