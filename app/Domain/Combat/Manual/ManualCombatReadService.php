<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\Manual\Data\ManualCombatState;
use App\Models\CombatSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Domain\Combat\Manual\Rewards\ManualCombatRewardSummaryService;
use App\Models\Character;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ManualCombatReadService
{
    private $events; private $rewards; private $expiration; private $recovery; private $policy;
    public function __construct(ManualCombatEventService $events, ManualCombatRewardSummaryService $rewards, ManualCombatExpirationService $expiration, ManualCombatRecoveryService $recovery, ManualCombatExpirationPolicy $policy) { $this->events = $events; $this->rewards = $rewards; $this->expiration=$expiration; $this->recovery=$recovery; $this->policy=$policy; }

    public function readFresh(User $user,CombatSession $combat,$afterSequence=0)
    {
        return DB::transaction(function()use($user,$combat,$afterSequence){
            $lockedCharacter=Character::whereKey($combat->character_id)->lockForUpdate()->firstOrFail();
            $lockedCombat=CombatSession::whereKey($combat->id)->lockForUpdate()->firstOrFail();
            if((int)$lockedCharacter->user_id!==(int)$user->id||(int)$lockedCombat->owner_user_id!==(int)$user->id)throw new AuthorizationException('This combat does not belong to the authenticated user.');
            $this->expiration->expireIfNeededLocked($lockedCombat);
            if($lockedCombat->status===ManualCombatStatus::ACTIVE)$this->recovery->recoverLocked($lockedCombat);
            return$this->read($user,$lockedCombat->fresh(),$afterSequence);
        },3);
    }

    public function read(User $user, CombatSession $combat, $afterSequence = 0)
    {
        if ((int) $combat->owner_user_id !== (int) $user->id) throw new AuthorizationException('This combat does not belong to the authenticated user.');
        $combat->loadMissing('participants');
        $participants = $combat->participants->sortBy('initiative_position')->values();
        $waiting = $combat->status === ManualCombatStatus::WAITING_PLAYER;
        $lastSequence = $this->events->lastSequence($combat);

        $rewardSummary=$this->rewards->forCombat($combat);$now=CarbonImmutable::now();$expiresAt=$this->policy->canExpire($combat)?$this->policy->expiresAt($combat):null;
        return new ManualCombatState([
            'combat_id' => (int) $combat->id,
            'status' => (string) $combat->status,
            'round' => (int) $combat->round_number,
            'lock_version' => (int) $combat->lock_version,
            'current_participant_id' => $combat->current_participant_id === null ? null : (int) $combat->current_participant_id,
            'actions_available' => $waiting ? ['basic_attack'] : [],
            'participants' => $participants->map(function ($participant) use ($combat, $waiting) {
                return [
                    'id' => (int) $participant->id,
                    'team' => (string) $participant->team,
                    'type' => (string) $participant->participant_type,
                    'name' => (string) $participant->display_name,
                    'current_hp' => (int) $participant->current_hp,
                    'max_hp' => (int) $participant->max_hp,
                    'status' => (string) $participant->status,
                    'initiative_position' => $participant->initiative_position === null ? null : (int) $participant->initiative_position,
                    'selectable' => $waiting && $participant->participant_type === CombatParticipantType::MONSTER && $participant->status === CombatParticipantStatus::ALIVE,
                    'is_current_turn' => (int) $participant->id === (int) $combat->current_participant_id,
                ];
            })->all(),
            'events' => $this->events->publicEvents($combat, (int) $afterSequence),
            'last_event_sequence' => $lastSequence,
            'rewards' => $rewardSummary,
            'can_abandon' => in_array($combat->status,[ManualCombatStatus::ACTIVE,ManualCombatStatus::WAITING_PLAYER],true),
            'expires_at' => $expiresAt?$expiresAt->toIso8601String():null,
            'seconds_until_expiration' => $expiresAt?max(0,$now->diffInSeconds($expiresAt,false)):null,
            'terminal_summary' => in_array($combat->status, ManualCombatStatus::terminalValues(), true) ? ['status' => (string) $combat->status,'result'=>(string)$combat->status,'rewards_status'=>$rewardSummary['status'], 'completed_at' => $combat->completed_at ? $combat->completed_at->toIso8601String() : null] : null,
        ]);
    }
}
