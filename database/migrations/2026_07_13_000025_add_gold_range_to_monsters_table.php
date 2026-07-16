<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoldRangeToMonstersTable extends Migration
{
    public function up()
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->unsignedBigInteger('gold_min')->default(0)->after('experience_reward');
            $table->unsignedBigInteger('gold_max')->default(0)->after('gold_min');
        });
    }

    public function down()
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropColumn(['gold_min', 'gold_max']);
        });
    }
}
