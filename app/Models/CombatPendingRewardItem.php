<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatPendingRewardItem extends Model
{
    protected $guarded = [];
    protected $casts = ['quantity' => 'integer', 'generation_metadata' => 'array', 'item_rarity_id' => 'integer', 'rarity_roll_metadata' => 'array'];

    public function reward() { return $this->belongsTo(CombatPendingReward::class, 'combat_pending_reward_id'); }
    public function item() { return $this->belongsTo(Item::class); }
    public function lootEntry() { return $this->belongsTo(MonsterLootEntry::class, 'loot_entry_id'); }
    public function itemRarity() { return $this->belongsTo(ItemRarity::class); }
}
