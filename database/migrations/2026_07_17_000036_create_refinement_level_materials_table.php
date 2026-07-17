<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefinementLevelMaterialsTable extends Migration
{
    public function up()
    {
        Schema::create('refinement_level_materials', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('refinement_level_id')->constrained('refinement_levels')->onDelete('restrict');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();
            $table->unique(['refinement_level_id', 'item_id'], 'refinement_material_rule_item_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refinement_level_materials');
    }
}
