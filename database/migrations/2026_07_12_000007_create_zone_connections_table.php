<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateZoneConnectionsTable extends Migration {
    public function up() { Schema::create('zone_connections', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->foreignId('from_zone_id')->constrained('zones')->cascadeOnDelete(); $table->foreignId('to_zone_id')->constrained('zones')->cascadeOnDelete(); $table->string('travel_type',20); $table->boolean('is_bidirectional')->default(true); $table->unsignedInteger('minimum_level')->default(1); $table->foreignId('required_item_id')->nullable()->constrained('items')->nullOnDelete(); $table->string('status',20); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->unique(['from_zone_id','to_zone_id']); }); }
    public function down() { Schema::dropIfExists('zone_connections'); }
}
