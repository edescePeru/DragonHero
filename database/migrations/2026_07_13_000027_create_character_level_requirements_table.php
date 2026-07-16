<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCharacterLevelRequirementsTable extends Migration
{
    public function up()
    {
        Schema::create('character_level_requirements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedInteger('level')->unique();
            $table->unsignedBigInteger('required_experience')->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('character_level_requirements');
    }
}
