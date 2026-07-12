<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCharactersTable extends Migration
{
    public function up()
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32)->unique();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->unsignedInteger('current_health')->default(100);
            $table->unsignedInteger('base_max_health')->default(100);
            $table->unsignedInteger('base_attack')->default(10);
            $table->unsignedInteger('base_defense')->default(5);
            $table->unsignedInteger('base_accuracy')->default(80);
            $table->unsignedInteger('base_evasion')->default(5);
            $table->unsignedDecimal('base_critical_rate', 5, 2)->default(5.00);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('characters');
    }
}
