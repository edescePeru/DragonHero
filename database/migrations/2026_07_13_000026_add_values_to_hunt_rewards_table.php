<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddValuesToHuntRewardsTable extends Migration
{
    public function up()
    {
        Schema::table('hunt_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('gold_amount')->default(0)->after('status');
            $table->unsignedBigInteger('experience_amount')->default(0)->after('gold_amount');
        });
    }

    public function down()
    {
        Schema::table('hunt_rewards', function (Blueprint $table) {
            $table->dropColumn(['gold_amount', 'experience_amount']);
        });
    }
}
