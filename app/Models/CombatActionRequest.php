<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombatActionRequest extends Model
{
    protected $guarded = [];
    protected $casts = [
        'request_payload' => 'array', 'expected_lock_version' => 'integer',
        'lock_version_before' => 'integer', 'lock_version_after' => 'integer',
        'first_event_sequence' => 'integer', 'last_event_sequence' => 'integer',
        'response_payload' => 'array', 'processed_at' => 'datetime',
    ];
    public function combatSession() { return $this->belongsTo(CombatSession::class); }
    public function actorParticipant() { return $this->belongsTo(CombatParticipant::class, 'actor_participant_id'); }
}
