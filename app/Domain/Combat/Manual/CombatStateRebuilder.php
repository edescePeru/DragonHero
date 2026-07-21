<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatSide;
use App\Domain\Combat\CombatTurnOrder;
use App\Domain\Combat\CombatStateStatus;
use App\Domain\Combat\Data\CombatantStats;
use App\Domain\Combat\Data\CombatParticipantState;
use App\Domain\Combat\Data\CombatState;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use InvalidArgumentException;

final class CombatStateRebuilder
{
    private $turnOrder;

    public function __construct(CombatTurnOrder $turnOrder)
    {
        $this->turnOrder = $turnOrder;
    }

    public function initialOrder($participants)
    {
        $states = [];
        foreach ($participants as $participant) {
            if (!$participant instanceof CombatParticipant) throw new InvalidArgumentException('Invalid combat participant.');
            $states[] = $this->state($participant);
        }
        return $this->turnOrder->build($states);
    }

    public function state(CombatParticipant $participant)
    {
        $snapshot = $participant->stats_snapshot;
        if (!is_array($snapshot)) throw new InvalidArgumentException('Missing participant stats snapshot.');
        $required = ['max_health', 'attack', 'defense', 'accuracy_rate', 'evasion_rate', 'critical_chance', 'critical_damage_multiplier', 'attack_speed', 'damage_reduction_rate'];
        foreach ($required as $key) if (!array_key_exists($key, $snapshot)) throw new InvalidArgumentException('Incomplete participant stats snapshot.');

        $stats = new CombatantStats(
            $participant->source_identifier,
            $participant->display_name,
            (int) $snapshot['max_health'],
            (int) $participant->current_hp,
            (int) $snapshot['attack'],
            (int) $snapshot['defense'],
            (float) $snapshot['accuracy_rate'],
            (float) $snapshot['evasion_rate'],
            (float) $snapshot['critical_chance'],
            (float) $snapshot['critical_damage_multiplier'],
            (float) $snapshot['attack_speed'],
            (float) $snapshot['damage_reduction_rate']
        );

        $team = $participant->team === CombatSide::PLAYERS ? CombatSide::PLAYERS : CombatSide::ENEMIES;
        return new CombatParticipantState($stats, (int) $participant->current_hp, $team, (int) $participant->position);
    }

    public function activeState(CombatSession $combat, $participants)
    {
        $ordered = $participants->filter(function ($participant) { return $participant->status === CombatParticipantStatus::ALIVE; })
            ->sortBy('initiative_position')->values();
        $states = $participants->map(function ($participant) { return $this->state($participant); })->all();
        $order = $ordered->pluck('source_identifier')->all();
        $current = $participants->firstWhere('id', (int) $combat->current_participant_id);
        if (!$current) throw new InvalidArgumentException('Current combat participant is missing.');
        $index = array_search($current->source_identifier, $order, true);
        if ($index === false) throw new InvalidArgumentException('Current combat participant is not active.');
        return new CombatState($states, (int) $combat->round_number, $order, $index, CombatStateStatus::IN_PROGRESS);
    }
}
