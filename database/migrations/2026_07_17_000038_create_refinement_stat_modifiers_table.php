<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
class CreateRefinementStatModifiersTable extends Migration{public function up(){Schema::create('refinement_stat_modifiers',function(Blueprint $t){$t->engine='InnoDB';$t->charset='utf8mb4';$t->collation='utf8mb4_unicode_ci';$t->id();$t->unsignedTinyInteger('refinement_level')->unique();$t->unsignedInteger('stat_increase_basis_points');$t->string('status',20);$t->timestamps();});}public function down(){Schema::dropIfExists('refinement_stat_modifiers');}}
