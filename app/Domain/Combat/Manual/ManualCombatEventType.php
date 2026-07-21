<?php
namespace App\Domain\Combat\Manual;
final class ManualCombatEventType
{
    const COMBAT_STARTED = 'combat_started'; const ROUND_STARTED = 'round_started'; const TURN_STARTED = 'turn_started';
    const BASIC_ATTACK = 'basic_attack'; const PARTICIPANT_DEFEATED = 'participant_defeated'; const TURN_FINISHED = 'turn_finished';
    const COMBAT_WON = 'combat_won'; const COMBAT_LOST = 'combat_lost';
    const REWARD_GENERATED = 'reward_generated'; const REWARDS_GRANTED = 'rewards_granted';
    const REWARDS_PENDING_CLAIM = 'rewards_pending_claim'; const REWARDS_FORFEITED = 'rewards_forfeited';
    const COMBAT_ABANDONED = 'combat_abandoned'; const COMBAT_EXPIRED = 'combat_expired';
}
