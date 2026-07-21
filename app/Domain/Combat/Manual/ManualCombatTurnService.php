<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatActionResolver;
use App\Domain\Combat\CombatActionType;
use App\Domain\Combat\CombatSide;
use App\Domain\Combat\Data\CombatCommand;
use App\Models\CombatSession;
use RuntimeException;

final class ManualCombatTurnService
{
    const MAX_AUTOMATIC_STEPS_PER_OPERATION = 50;
    private $rebuilder; private $selector; private $resolver; private $persistence;

    public function __construct(CombatStateRebuilder $rebuilder, AutomaticCombatActionSelector $selector, CombatActionResolver $resolver, ManualCombatStatePersistenceService $persistence)
    {
        $this->rebuilder = $rebuilder; $this->selector = $selector; $this->resolver = $resolver; $this->persistence = $persistence;
    }

    public function advanceAutomaticLocked(CombatSession $combat, $participants)
    {
        $steps = 0;
        while ($combat->status === ManualCombatStatus::ACTIVE) {
            if (++$steps > self::MAX_AUTOMATIC_STEPS_PER_OPERATION) throw new RuntimeException('Automatic combat step limit exceeded.');
            $actor = $participants->firstWhere('id', (int) $combat->current_participant_id);
            if (!$actor || $actor->team !== CombatSide::ENEMIES || $actor->status !== CombatParticipantStatus::ALIVE) throw new RuntimeException('Invalid automatic combat actor.');
            $target = $this->selector->target($participants);
            $state = $this->rebuilder->activeState($combat, $participants);
            $step = $this->resolver->resolve($state, new CombatCommand(null, $actor->source_identifier, CombatActionType::BASIC_ATTACK, $target->source_identifier));
            $this->persistence->apply($combat, $participants, $step);
        }
        return $steps;
    }
}
