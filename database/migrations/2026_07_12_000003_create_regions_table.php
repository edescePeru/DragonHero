<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateRegionsTable extends Migration {
    public function up() { Schema::create('regions', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->foreignId('world_id')->constrained()->cascadeOnDelete(); $table->string('code',64); $table->string('name'); $table->text('description')->nullable(); $table->unsignedInteger('recommended_level_min')->default(1); $table->unsignedInteger('recommended_level_max')->nullable(); $table->string('status',20); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->unique(['world_id','code']); }); }
    public function down() { Schema::dropIfExists('regions'); }
}
