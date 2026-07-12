<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateCharacterItemsTable extends Migration {
 public function up(){Schema::create('character_items',function(Blueprint $table){$table->engine='InnoDB';$table->charset='utf8mb4';$table->collation='utf8mb4_unicode_ci';$table->id();$table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();$table->foreignId('item_id')->constrained('items')->onDelete('restrict');$table->unsignedBigInteger('quantity')->default(0);$table->unsignedBigInteger('locked_quantity')->default(0);$table->timestamps();$table->unique(['character_id','item_id']);});}
 public function down(){Schema::dropIfExists('character_items');}
}
