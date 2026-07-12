<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateItemsTable extends Migration {
    public function up() { Schema::create('items', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->string('code',64)->unique(); $table->string('name'); $table->text('description')->nullable(); $table->string('item_type',30); $table->string('rarity',20); $table->boolean('is_stackable'); $table->unsignedInteger('max_stack')->nullable(); $table->string('status',20); $table->timestamps(); }); }
    public function down() { Schema::dropIfExists('items'); }
}
