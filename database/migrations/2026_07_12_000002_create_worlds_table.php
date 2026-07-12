<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateWorldsTable extends Migration {
    public function up() { Schema::create('worlds', function (Blueprint $table) { $table->engine='InnoDB'; $table->charset='utf8mb4'; $table->collation='utf8mb4_unicode_ci'; $table->id(); $table->string('code',64)->unique(); $table->string('name'); $table->text('description')->nullable(); $table->string('status',20); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); }); }
    public function down() { Schema::dropIfExists('worlds'); }
}
