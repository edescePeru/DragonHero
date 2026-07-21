<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManualCombatPendingRewards extends Migration
{
    public function up()
    {
        Schema::create('combat_pending_rewards', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->onDelete('restrict');
            $table->foreignId('source_participant_id')->constrained('combat_participants')->onDelete('restrict');
            $table->unsignedBigInteger('source_monster_id');
            $table->string('source_identifier', 191);
            $table->unsignedBigInteger('experience_amount')->default(0);
            $table->unsignedBigInteger('gold_amount')->default(0);
            $table->string('status', 24);
            $table->json('generation_context')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();
            $table->timestamps();
            $table->unique(['combat_session_id', 'source_participant_id'], 'combat_pending_rewards_source_unique');
            $table->index(['combat_session_id', 'status']);
            $table->index('source_monster_id');
        });

        Schema::create('combat_pending_reward_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('combat_pending_reward_id')->constrained('combat_pending_rewards')->onDelete('restrict');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->string('item_code_snapshot', 191);
            $table->string('item_name_snapshot', 191);
            $table->unsignedBigInteger('quantity');
            $table->foreignId('loot_entry_id')->nullable()->constrained('monster_loot_entries')->onDelete('set null');
            $table->json('generation_metadata')->nullable();
            $table->timestamps();
            $table->index(['combat_pending_reward_id', 'item_id'], 'combat_pending_reward_items_reward_item_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('combat_pending_reward_items');
        Schema::dropIfExists('combat_pending_rewards');
    }
}
