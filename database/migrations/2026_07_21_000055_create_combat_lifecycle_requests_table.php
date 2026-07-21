<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCombatLifecycleRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('combat_lifecycle_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->onDelete('restrict');
            $table->string('client_request_id', 36);
            $table->string('request_type', 24);
            $table->unsignedBigInteger('expected_lock_version');
            $table->unsignedBigInteger('lock_version_before');
            $table->unsignedBigInteger('lock_version_after')->nullable();
            $table->string('status', 20);
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['combat_session_id', 'client_request_id'], 'combat_lifecycle_requests_idempotency_unique');
            $table->index(['combat_session_id', 'request_type', 'status'], 'combat_lifecycle_requests_state_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('combat_lifecycle_requests');
    }
}
