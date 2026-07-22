<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class CreateNpcsTable extends Migration{public function up(){Schema::create('npcs',function(Blueprint $t){$t->engine='InnoDB';$t->charset='utf8mb4';$t->collation='utf8mb4_unicode_ci';$t->id();$t->string('code',64)->unique();$t->string('name');$t->string('greeting',500)->nullable();$t->string('status',20);$t->timestamps();$t->index(['status','name']);});}public function down(){Schema::dropIfExists('npcs');}}
