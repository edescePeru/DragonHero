<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateCharacterWalletsTable extends Migration {
 public function up(){Schema::create('character_wallets',function(Blueprint $t){$t->engine='InnoDB';$t->charset='utf8mb4';$t->collation='utf8mb4_unicode_ci';$t->id();$t->foreignId('character_id')->unique()->constrained('characters')->cascadeOnDelete();$t->unsignedBigInteger('gold_balance')->default(0);$t->timestamps();});}
 public function down(){Schema::dropIfExists('character_wallets');}
}
