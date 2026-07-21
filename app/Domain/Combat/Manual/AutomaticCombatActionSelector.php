<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatSide;
use InvalidArgumentException;

final class AutomaticCombatActionSelector
{
    public function target($participants)
    {
        $targets = $participants->filter(function ($participant) {
            return $participant->team === CombatSide::PLAYERS && $participant->status === CombatParticipantStatus::ALIVE && $participant->current_hp > 0;
        })->sort(function ($left, $right) {
            $position = $left->position <=> $right->position;
            return $position !== 0 ? $position : ($left->id <=> $right->id);
        })->values();
        if ($targets->isEmpty()) throw new InvalidArgumentException('No living player target exists.');
        return $targets->first();
    }
}
