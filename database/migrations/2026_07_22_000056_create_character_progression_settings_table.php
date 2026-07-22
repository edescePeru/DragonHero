<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCharacterProgressionSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('character_progression_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB'; $table->charset = 'utf8mb4'; $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->unsignedInteger('max_character_level');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        $maxLevel = (int) DB::table('character_level_requirements')->max('level');
        if ($maxLevel < 1) {
            $now = now();
            DB::table('character_level_requirements')->insert(['level' => 1, 'required_experience' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $maxLevel = 1;
        }
        DB::table('character_progression_settings')->insert(['id' => 1, 'max_character_level' => $maxLevel, 'version' => 1, 'updated_by' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down() { Schema::dropIfExists('character_progression_settings'); }
}
