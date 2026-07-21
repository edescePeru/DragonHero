<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatActionResolver;
use App\Domain\Combat\CombatActionType;
use App\Domain\Combat\CombatSide;
use App\Domain\Combat\Data\CombatCommand;
use App\Domain\Combat\Manual\Data\ManualCombatActionResult;
use App\Domain\Combat\Manual\Exceptions\ManualCombatConflictException;
use App\Models\Character;
use App\Models\CombatActionRequest;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ManualCombatActionService
{
    private $rebuilder; private $resolver; private $persistence; private $turns; private $events; private $read; private $expiration;
    public function __construct(CombatStateRebuilder $rebuilder, CombatActionResolver $resolver, ManualCombatStatePersistenceService $persistence, ManualCombatTurnService $turns, ManualCombatEventService $events, ManualCombatReadService $read, ManualCombatExpirationService $expiration)
    { $this->rebuilder=$rebuilder; $this->resolver=$resolver; $this->persistence=$persistence; $this->turns=$turns; $this->events=$events; $this->read=$read; $this->expiration=$expiration; }

    public function execute(User $user, Character $character, CombatSession $combat, array $input)
    {
        $result=DB::transaction(function () use ($user, $character, $combat, $input) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            if ((int)$lockedCharacter->user_id !== (int)$user->id) throw new AuthorizationException();
            $lockedCombat = CombatSession::whereKey($combat->id)->lockForUpdate()->firstOrFail();
            if ((int)$lockedCombat->owner_user_id !== (int)$user->id || (int)$lockedCombat->character_id !== (int)$lockedCharacter->id) throw new AuthorizationException();

            $existing = CombatActionRequest::where('combat_session_id',$lockedCombat->id)->where('client_action_id',$input['client_action_id'])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status === ManualCombatActionRequestStatus::PROCESSED && is_array($existing->response_payload)) {
                    $response=$existing->response_payload; $response['idempotent_replay']=true; return new ManualCombatActionResult($response);
                }
                throw new ManualCombatConflictException('The action is still processing or was not completed.');
            }

            if($this->expiration->expireIfNeededLocked($lockedCombat))return '__manual_combat_expired__';

            if ((int)$lockedCombat->active_slot !== (int)$lockedCharacter->id || $lockedCombat->status !== ManualCombatStatus::WAITING_PLAYER) throw new ManualCombatConflictException('The combat is not waiting for a player action.');
            if ((int)$input['expected_lock_version'] !== (int)$lockedCombat->lock_version) throw new ManualCombatConflictException('The combat state version is stale.');
            $participants = CombatParticipant::where('combat_session_id',$lockedCombat->id)->orderBy('id')->lockForUpdate()->get();
            $actor = $participants->firstWhere('id',(int)$lockedCombat->current_participant_id);
            if (!$actor || $actor->participant_type !== CombatParticipantType::CHARACTER || $actor->team !== CombatSide::PLAYERS || (int)$actor->owner_user_id !== (int)$user->id || $actor->status !== CombatParticipantStatus::ALIVE) throw new ManualCombatConflictException('The current participant cannot act.');
            $target = $participants->firstWhere('id',(int)$input['target_participant_id']);
            if (!$target || $target->team !== CombatSide::ENEMIES || $target->participant_type !== CombatParticipantType::MONSTER) throw new InvalidArgumentException('The selected target is not an enemy in this combat.');
            if ($target->status !== CombatParticipantStatus::ALIVE || $target->current_hp <= 0) throw new ManualCombatConflictException('The selected target is already defeated.');

            $before=(int)$lockedCombat->lock_version;
            $request=CombatActionRequest::create(['combat_session_id'=>$lockedCombat->id,'client_action_id'=>$input['client_action_id'],'actor_participant_id'=>$actor->id,'action_type'=>CombatActionType::BASIC_ATTACK,'request_payload'=>['target_participant_id'=>(int)$target->id],'expected_lock_version'=>(int)$input['expected_lock_version'],'lock_version_before'=>$before,'status'=>ManualCombatActionRequestStatus::PROCESSING]);
            $previousSequence=$this->events->lastSequence($lockedCombat);
            $state=$this->rebuilder->activeState($lockedCombat,$participants);
            $step=$this->resolver->resolve($state,new CombatCommand($input['client_action_id'],$actor->source_identifier,CombatActionType::BASIC_ATTACK,$target->source_identifier));
            $this->persistence->apply($lockedCombat,$participants,$step);
            $this->turns->advanceAutomaticLocked($lockedCombat,$participants);
            $lockedCombat->lock_version=$before+1;
            $lockedCombat->last_action_at=CarbonImmutable::now();
            $lockedCombat->save();
            $lastSequence=$this->events->lastSequence($lockedCombat);
            $firstSequence=$lastSequence>$previousSequence?$previousSequence+1:null;
            $combatState=$this->read->read($user,$lockedCombat->fresh(),$lastSequence)->toArray();
            unset($combatState['events']);
            $response=['action_request_id'=>(int)$request->id,'idempotent_replay'=>false,'combat'=>$combatState,'events'=>$this->events->publicEvents($lockedCombat,$previousSequence,$lastSequence),'first_event_sequence'=>$firstSequence,'last_event_sequence'=>$lastSequence];
            $request->status=ManualCombatActionRequestStatus::PROCESSED;
            $request->lock_version_after=$before+1;
            $request->first_event_sequence=$firstSequence;
            $request->last_event_sequence=$lastSequence;
            $request->response_payload=$response;
            $request->processed_at=CarbonImmutable::now();
            $request->save();
            return new ManualCombatActionResult($response);
        },3);
        if($result==='__manual_combat_expired__')throw new ManualCombatConflictException('The combat has expired.');
        return$result;
    }
}
