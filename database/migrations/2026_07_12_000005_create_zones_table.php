<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateZonesTable extends Migration {
    public function up() { Schema::create('zones', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->foreignId('region_id')->constrained()->cascadeOnDelete(); $table->string('code',64); $table->string('name'); $table->text('description')->nullable(); $table->string('zone_type',30); $table->unsignedInteger('recommended_level_min')->default(1); $table->unsignedInteger('recommended_level_max')->nullable(); $table->boolean('is_safe')->default(false); $table->boolean('allows_hunting')->default(true); $table->string('status',20); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->unique(['region_id','code']); }); }
    public function down() { Schema::dropIfExists('zones'); }
}
