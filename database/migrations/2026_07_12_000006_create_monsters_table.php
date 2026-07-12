<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateMonstersTable extends Migration {
    public function up() { Schema::create('monsters', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->string('code',64)->unique(); $table->string('name'); $table->text('description')->nullable(); $table->unsignedInteger('level'); $table->unsignedInteger('max_health'); $table->unsignedInteger('attack'); $table->unsignedInteger('defense'); $table->unsignedDecimal('accuracy_rate',5,2); $table->unsignedDecimal('evasion_rate',5,2); $table->unsignedDecimal('critical_chance',5,2); $table->unsignedBigInteger('experience_reward')->default(0); $table->string('status',20); $table->timestamps(); }); }
    public function down() { Schema::dropIfExists('monsters'); }
}
