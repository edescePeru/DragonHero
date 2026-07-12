<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateZoneMonstersTable extends Migration {
    public function up() { Schema::create('zone_monsters', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->foreignId('zone_id')->constrained()->cascadeOnDelete(); $table->foreignId('monster_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('weight'); $table->unsignedInteger('minimum_character_level')->default(1); $table->unsignedInteger('maximum_character_level')->nullable(); $table->string('status',20); $table->timestamps(); $table->unique(['zone_id','monster_id']); }); }
    public function down() { Schema::dropIfExists('zone_monsters'); }
}
