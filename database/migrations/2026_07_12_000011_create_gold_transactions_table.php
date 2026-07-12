<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateGoldTransactionsTable extends Migration {
 public function up(){Schema::create('gold_transactions',function(Blueprint $t){$t->engine='InnoDB';$t->charset='utf8mb4';$t->collation='utf8mb4_unicode_ci';$t->id();$t->foreignId('character_id')->constrained('characters')->onDelete('restrict');$t->string('transaction_type',16);$t->unsignedBigInteger('amount');$t->unsignedBigInteger('balance_before');$t->unsignedBigInteger('balance_after');$t->string('reason_code',64);$t->string('description')->nullable();$t->string('reference_type',100)->nullable();$t->unsignedBigInteger('reference_id')->nullable();$t->string('idempotency_key',191)->nullable()->unique();$t->timestamps();$t->index(['character_id','created_at']);$t->index('reason_code');$t->index(['reference_type','reference_id']);});}
 public function down(){Schema::dropIfExists('gold_transactions');}
}
