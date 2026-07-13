<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryCapacityGrantsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_capacity_grants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedInteger('slots');
            $table->string('source_type', 64);
            $table->string('source_identifier', 191);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->foreign('character_id')->references('id')->on('characters')->onDelete('cascade');
            $table->unique(['character_id', 'source_type', 'source_identifier'], 'capacity_grants_logical_unique');
            $table->index(['character_id', 'is_active', 'starts_at', 'ends_at'], 'capacity_grants_active_window');
        });
    }

    public function down(){ Schema::dropIfExists('inventory_capacity_grants'); }
}
