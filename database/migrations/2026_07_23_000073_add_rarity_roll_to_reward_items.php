<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRarityRollToRewardItems extends Migration
{
    public function up()
    {
        foreach (['hunt_reward_items', 'combat_pending_reward_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('item_rarity_id')->nullable();
                $table->string('rarity_code_snapshot', 32)->nullable();
                $table->string('rarity_name_snapshot', 64)->nullable();
                $table->json('rarity_roll_metadata')->nullable();
                $table->foreign('item_rarity_id')->references('id')->on('item_rarities')->onDelete('restrict');
            });
        }
    }

    public function down()
    {
        foreach (['combat_pending_reward_items', 'hunt_reward_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign([$name === 'hunt_reward_items' ? 'item_rarity_id' : 'item_rarity_id']);
                $table->dropColumn(['item_rarity_id', 'rarity_code_snapshot', 'rarity_name_snapshot', 'rarity_roll_metadata']);
            });
        }
    }
}
