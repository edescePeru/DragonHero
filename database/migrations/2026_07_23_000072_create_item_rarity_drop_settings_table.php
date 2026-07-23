<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateItemRarityDropSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('item_rarity_drop_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedInteger('common_probability_ppm');
            $table->unsignedInteger('rare_probability_ppm');
            $table->unsignedInteger('mythic_probability_ppm');
            $table->unsignedInteger('legendary_probability_ppm');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
        DB::table('item_rarity_drop_settings')->insert([
            'id' => 1, 'common_probability_ppm' => 949000, 'rare_probability_ppm' => 49000,
            'mythic_probability_ppm' => 1950, 'legendary_probability_ppm' => 50,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down() { Schema::dropIfExists('item_rarity_drop_settings'); }
}
