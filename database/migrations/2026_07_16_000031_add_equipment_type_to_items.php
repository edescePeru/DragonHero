<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class AddEquipmentTypeToItems extends Migration{public function up(){Schema::table('items',function(Blueprint $t){$t->string('equipment_type',32)->nullable()->after('item_type')->index();});}public function down(){Schema::table('items',function(Blueprint $t){$t->dropIndex(['equipment_type']);$t->dropColumn('equipment_type');});}}
