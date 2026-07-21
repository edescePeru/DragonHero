<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatParticipant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'source_id' => 'integer',
        'owner_user_id' => 'integer',
        'current_hp' => 'integer',
        'max_hp' => 'integer',
        'stats_snapshot' => 'array',
        'initiative_position' => 'integer',
        'defeated_at' => 'datetime',
    ];

    public function combatSession() { return $this->belongsTo(CombatSession::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function actionRequests() { return $this->hasMany(CombatActionRequest::class, 'actor_participant_id'); }
    public function events() { return $this->hasMany(CombatEvent::class, 'actor_participant_id'); }
    public function pendingReward() { return $this->hasOne(CombatPendingReward::class, 'source_participant_id'); }
}
