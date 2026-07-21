<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManualCombatBaseTables extends Migration
{
    public function up()
    {
        Schema::create('combat_sessions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('character_id')->constrained('characters')->onDelete('restrict');
            $table->foreignId('hunting_session_id')->constrained('hunting_sessions')->onDelete('restrict');
            $table->foreignId('zone_id')->constrained('zones')->onDelete('restrict');
            $table->string('mode', 20);
            $table->string('status', 32);
            $table->unsignedInteger('round_number')->default(1);
            $table->unsignedBigInteger('current_participant_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->unsignedBigInteger('active_slot')->nullable()->unique();
            $table->timestamp('started_at');
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rewards_granted_at')->nullable();
            $table->timestamps();
            $table->index(['owner_user_id', 'created_at']);
            $table->index(['character_id', 'created_at']);
            $table->index(['hunting_session_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('combat_participants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->cascadeOnDelete();
            $table->string('team', 20);
            $table->unsignedInteger('position');
            $table->string('participant_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('display_name', 191);
            $table->string('source_identifier', 191);
            $table->unsignedInteger('current_hp');
            $table->unsignedInteger('max_hp');
            $table->string('status', 20);
            $table->json('stats_snapshot');
            $table->unsignedInteger('initiative_position')->nullable();
            $table->timestamp('defeated_at')->nullable();
            $table->timestamps();
            $table->unique(['combat_session_id', 'team', 'position'], 'combat_participants_team_position_unique');
            $table->unique(['combat_session_id', 'source_identifier'], 'combat_participants_identifier_unique');
            $table->index(['participant_type', 'source_id']);
        });

        Schema::table('combat_sessions', function (Blueprint $table) {
            $table->foreign('current_participant_id')
                ->references('id')
                ->on('combat_participants')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('combat_sessions', function (Blueprint $table) {
            $table->dropForeign(['current_participant_id']);
        });
        Schema::dropIfExists('combat_participants');
        Schema::dropIfExists('combat_sessions');
    }
}
