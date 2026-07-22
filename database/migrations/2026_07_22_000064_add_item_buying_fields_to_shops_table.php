<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class AddItemBuyingFieldsToShopsTable extends Migration{public function up(){Schema::table('shops',function(Blueprint $t){$t->boolean('buys_items')->default(false)->after('description');$t->unsignedInteger('purchase_rate_basis_points')->default(10000)->after('buys_items');});}public function down(){Schema::table('shops',function(Blueprint $t){$t->dropColumn(['buys_items','purchase_rate_basis_points']);});}}
