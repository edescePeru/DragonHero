<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefinementLevelsTable extends Migration
{
    public function up()
    {
        Schema::create('refinement_levels', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedTinyInteger('from_level')->unique();
            $table->unsignedTinyInteger('to_level');
            $table->unsignedSmallInteger('success_chance_basis_points')->default(10000);
            $table->unsignedBigInteger('gold_cost')->default(0);
            $table->string('failure_behavior', 32)->default('keep_level');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('refinement_levels');
    }
}
