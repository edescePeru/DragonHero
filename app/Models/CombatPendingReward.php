<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatPendingReward extends Model
{
    protected $guarded = [];

    protected $casts = [
        'experience_amount' => 'integer',
        'gold_amount' => 'integer',
        'generation_context' => 'array',
        'generated_at' => 'datetime',
        'granted_at' => 'datetime',
        'forfeited_at' => 'datetime',
    ];

    public function combatSession() { return $this->belongsTo(CombatSession::class); }
    public function sourceParticipant() { return $this->belongsTo(CombatParticipant::class, 'source_participant_id'); }
    public function sourceMonster() { return $this->belongsTo(Monster::class, 'source_monster_id'); }
    public function items() { return $this->hasMany(CombatPendingRewardItem::class); }
}
