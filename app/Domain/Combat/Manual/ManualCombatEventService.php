<?php

namespace App\Domain\Combat\Manual;

use App\Models\CombatEvent;
use App\Models\CombatParticipant;
use App\Models\CombatSession;

final class ManualCombatEventService
{
    public function append(CombatSession $combat, $type, $round, CombatParticipant $actor = null, array $payload = [])
    {
        $sequence = (int) CombatEvent::where('combat_session_id', $combat->id)->max('sequence') + 1;
        return CombatEvent::create([
            'combat_session_id' => $combat->id,
            'sequence' => $sequence,
            'round_number' => (int) $round,
            'event_type' => $type,
            'actor_participant_id' => $actor ? $actor->id : null,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public function publicEvents(CombatSession $combat, $afterSequence = 0, $throughSequence = null)
    {
        $query = CombatEvent::where('combat_session_id', $combat->id)->where('sequence', '>', (int) $afterSequence)->orderBy('sequence');
        if ($throughSequence !== null) $query->where('sequence', '<=', (int) $throughSequence);
        return $query->get()->map(function ($event) {
            $payload = $event->payload;
            if ($event->event_type === ManualCombatEventType::BASIC_ATTACK && isset($payload['rolls'])) unset($payload['rolls']);
            return [
                'sequence' => (int) $event->sequence,
                'round' => (int) $event->round_number,
                'type' => (string) $event->event_type,
                'actor_participant_id' => $event->actor_participant_id === null ? null : (int) $event->actor_participant_id,
                'payload' => $payload,
            ];
        })->all();
    }

    public function lastSequence(CombatSession $combat)
    {
        return (int) CombatEvent::where('combat_session_id', $combat->id)->max('sequence');
    }
}
