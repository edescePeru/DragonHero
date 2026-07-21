<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManualCombatActionsAndEvents extends Migration
{
    public function up()
    {
        Schema::create('combat_action_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->cascadeOnDelete();
            $table->string('client_action_id', 36);
            $table->foreignId('actor_participant_id')->constrained('combat_participants')->onDelete('restrict');
            $table->string('action_type', 32);
            $table->json('request_payload')->nullable();
            $table->unsignedBigInteger('expected_lock_version')->nullable();
            $table->unsignedBigInteger('lock_version_before');
            $table->unsignedBigInteger('lock_version_after')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('first_event_sequence')->nullable();
            $table->unsignedInteger('last_event_sequence')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['combat_session_id', 'client_action_id'], 'combat_action_requests_idempotency_unique');
            $table->index(['combat_session_id', 'status']);
        });

        Schema::create('combat_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('round_number');
            $table->string('event_type', 32);
            $table->foreignId('actor_participant_id')->nullable()->constrained('combat_participants')->onDelete('restrict');
            $table->json('payload');
            $table->timestamp('created_at');
            $table->unique(['combat_session_id', 'sequence'], 'combat_events_sequence_unique');
            $table->index(['combat_session_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('combat_events');
        Schema::dropIfExists('combat_action_requests');
    }
}
