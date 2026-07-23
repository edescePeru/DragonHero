<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRarityToItemInstances extends Migration
{
    public function up()
    {
        Schema::table('item_instances', function (Blueprint $table) {
            $table->unsignedBigInteger('item_rarity_id')->nullable()->after('item_id');
            $table->index('item_rarity_id');
        });

        $commonId = DB::table('item_rarities')->where('code', 'common')->value('id');
        if (!$commonId) {
            throw new RuntimeException('Official common Item rarity is missing.');
        }
        DB::table('item_instances')->whereNull('item_rarity_id')->update(['item_rarity_id' => $commonId]);
        DB::statement('ALTER TABLE item_instances MODIFY item_rarity_id BIGINT UNSIGNED NOT NULL');

        Schema::table('item_instances', function (Blueprint $table) {
            $table->foreign('item_rarity_id')->references('id')->on('item_rarities')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('item_instances', function (Blueprint $table) {
            $table->dropForeign(['item_rarity_id']);
            $table->dropIndex(['item_rarity_id']);
            $table->dropColumn('item_rarity_id');
        });
    }
}
