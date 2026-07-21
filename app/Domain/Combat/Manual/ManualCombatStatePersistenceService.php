<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatResultStatus;
use App\Domain\Combat\Data\CombatStepResult;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Character;
use App\Domain\Combat\Manual\Rewards\ManualCombatRewardGenerationService;
use App\Domain\Combat\Manual\Rewards\ManualCombatRewardLifecycleService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ManualCombatStatePersistenceService
{
    private $events; private $rewardGeneration; private $rewardLifecycle; private $huntingSessions;

    public function __construct(ManualCombatEventService $events, ManualCombatRewardGenerationService $rewardGeneration, ManualCombatRewardLifecycleService $rewardLifecycle, ManualCombatHuntingSessionLifecycleService $huntingSessions) { $this->events = $events; $this->rewardGeneration = $rewardGeneration; $this->rewardLifecycle = $rewardLifecycle; $this->huntingSessions = $huntingSessions; }

    public function apply(CombatSession $combat, $participants, CombatStepResult $step)
    {
        $action = $step->resolvedAction();
        $actor = $participants->firstWhere('source_identifier', $action->attackerIdentifier());
        $target = $participants->firstWhere('source_identifier', $action->defenderIdentifier());
        if (!$actor || !$target) throw new InvalidArgumentException('Resolved combat participants are missing.');

        $wasAlive = $target->status === CombatParticipantStatus::ALIVE;
        $target->current_hp = (int) $action->defenderHealthAfter();
        if ($target->current_hp === 0) {
            $target->status = CombatParticipantStatus::DEFEATED;
            if ($wasAlive) $target->defeated_at = CarbonImmutable::now();
        }
        $target->save();

        $this->events->append($combat, ManualCombatEventType::BASIC_ATTACK, $action->roundNumber(), $actor, [
            'version' => 1,
            'action_type' => 'basic_attack',
            'actor' => $this->identity($actor),
            'targets' => [[
                'participant_id' => (int) $target->id,
                'name' => (string) $target->display_name,
                'type' => (string) $target->participant_type,
                'hit' => (bool) $action->hit(),
                'critical' => (bool) $action->critical(),
                'damage' => (int) $action->damage(),
                'hp_before' => (int) $action->defenderHealthBefore(),
                'hp_after' => (int) $action->defenderHealthAfter(),
                'defeated' => $target->current_hp === 0,
            ]],
            'rolls' => [
                'hit_roll_basis_points' => (int) round($action->randomRoll() * 100),
                'critical_roll_basis_points' => $action->criticalRoll() === null ? null : (int) round($action->criticalRoll() * 100),
            ],
        ]);

        if ($wasAlive && $target->status === CombatParticipantStatus::DEFEATED) {
            $this->events->append($combat, ManualCombatEventType::PARTICIPANT_DEFEATED, $action->roundNumber(), $actor, ['version' => 1, 'participant' => $this->identity($target)]);
            if ($target->participant_type === CombatParticipantType::MONSTER) $this->rewardGeneration->generateLocked($combat, $target, $action->roundNumber());
        }
        $this->events->append($combat, ManualCombatEventType::TURN_FINISHED, $action->roundNumber(), $actor, ['version' => 1, 'participant_id' => (int) $actor->id]);

        $next = $step->nextState();
        if ($next->status() !== 'in_progress') {
            $won = $next->status() === CombatResultStatus::CHARACTER_VICTORY;
            $combat->status = $won ? ManualCombatStatus::WON : ManualCombatStatus::LOST;
            $combat->current_participant_id = null;
            $combat->active_slot = null;
            $combat->completed_at = CarbonImmutable::now();
            $combat->last_action_at = CarbonImmutable::now();
            foreach ($participants as $participant) { $participant->initiative_position = null; $participant->save(); }
            $this->events->append($combat, $won ? ManualCombatEventType::COMBAT_WON : ManualCombatEventType::COMBAT_LOST, $action->roundNumber(), null, ['version' => 1]);
            $combat->save();
            if ($won) $this->rewardLifecycle->victoryLocked(Character::whereKey($combat->character_id)->firstOrFail(), $combat);
            else $this->rewardLifecycle->defeatLocked($combat);
            $this->huntingSessions->stopRelatedSessionLocked($combat, $combat->status, CarbonImmutable::now());
            return;
        }

        $roundChanged = (int) $next->roundNumber() !== (int) $combat->round_number;
        $combat->round_number = (int) $next->roundNumber();
        $order = $next->actionOrder();
        foreach ($participants as $participant) {
            $index = array_search($participant->source_identifier, $order, true);
            $participant->initiative_position = $index === false ? null : $index + 1;
            $participant->save();
        }
        if ($roundChanged) $this->events->append($combat, ManualCombatEventType::ROUND_STARTED, $combat->round_number, null, ['version' => 1, 'round' => (int) $combat->round_number]);
        $nextParticipant = $participants->firstWhere('source_identifier', $next->nextActorIdentifier());
        if (!$nextParticipant) throw new InvalidArgumentException('Next combat participant is missing.');
        $combat->current_participant_id = $nextParticipant->id;
        $combat->status = $nextParticipant->participant_type === CombatParticipantType::CHARACTER ? ManualCombatStatus::WAITING_PLAYER : ManualCombatStatus::ACTIVE;
        $combat->last_action_at = CarbonImmutable::now();
        $combat->save();
        $this->events->append($combat, ManualCombatEventType::TURN_STARTED, $combat->round_number, $nextParticipant, ['version' => 1, 'participant_id' => (int) $nextParticipant->id]);
    }

    private function identity(CombatParticipant $participant)
    {
        return ['participant_id' => (int) $participant->id, 'name' => (string) $participant->display_name, 'type' => (string) $participant->participant_type];
    }
}
