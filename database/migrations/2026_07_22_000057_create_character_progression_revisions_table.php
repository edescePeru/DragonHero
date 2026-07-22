<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCharacterProgressionRevisionsTable extends Migration
{
    public function up()
    {
        Schema::create('character_progression_revisions', function (Blueprint $table) {
            $table->engine = 'InnoDB'; $table->charset = 'utf8mb4'; $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedBigInteger('administrator_user_id')->nullable();
            $table->unsignedInteger('previous_max_level');
            $table->unsignedInteger('new_max_level');
            $table->json('previous_curve');
            $table->json('new_curve');
            $table->text('reason');
            $table->timestamps();
            $table->foreign('administrator_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down() { Schema::dropIfExists('character_progression_revisions'); }
}
