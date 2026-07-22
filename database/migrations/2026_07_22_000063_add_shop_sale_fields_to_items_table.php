<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class AddShopSaleFieldsToItemsTable extends Migration{public function up(){Schema::table('items',function(Blueprint $t){$t->boolean('is_sellable')->default(false)->after('max_stack');$t->unsignedBigInteger('sell_price')->default(0)->after('is_sellable');});}public function down(){Schema::table('items',function(Blueprint $t){$t->dropColumn(['is_sellable','sell_price']);});}}
