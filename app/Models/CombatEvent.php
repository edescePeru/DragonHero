<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CombatEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['sequence' => 'integer', 'round_number' => 'integer', 'actor_participant_id' => 'integer', 'payload' => 'array', 'created_at' => 'datetime'];
    protected static function booted()
    {
        static::updating(function () { throw new LogicException('Combat events are immutable.'); });
        static::deleting(function () { throw new LogicException('Combat events are immutable.'); });
    }
    public function combatSession() { return $this->belongsTo(CombatSession::class); }
    public function actorParticipant() { return $this->belongsTo(CombatParticipant::class, 'actor_participant_id'); }
}
