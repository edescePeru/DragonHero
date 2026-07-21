<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'round_number' => 'integer',
        'current_participant_id' => 'integer',
        'lock_version' => 'integer',
        'active_slot' => 'integer',
        'started_at' => 'datetime',
        'last_action_at' => 'datetime',
        'completed_at' => 'datetime',
        'rewards_granted_at' => 'datetime',
    ];

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function character() { return $this->belongsTo(Character::class); }
    public function huntingSession() { return $this->belongsTo(HuntingSession::class); }
    public function zone() { return $this->belongsTo(Zone::class); }
    public function participants() { return $this->hasMany(CombatParticipant::class)->orderBy('initiative_position')->orderBy('id'); }
    public function currentParticipant() { return $this->belongsTo(CombatParticipant::class, 'current_participant_id'); }
    public function actionRequests() { return $this->hasMany(CombatActionRequest::class); }
    public function events() { return $this->hasMany(CombatEvent::class)->orderBy('sequence'); }
    public function pendingRewards() { return $this->hasMany(CombatPendingReward::class)->orderBy('id'); }
    public function lifecycleRequests() { return $this->hasMany(CombatLifecycleRequest::class); }
}
