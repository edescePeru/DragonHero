<?php
namespace App\Domain\Combat\Manual;
use App\Domain\Combat\Manual\Data\ManualCombatLifecycleResult;
use App\Domain\Combat\Manual\Exceptions\ManualCombatConflictException;
use App\Models\Character;
use App\Models\CombatLifecycleRequest;
use App\Models\CombatSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
final class ManualCombatAbandonService
{
    private $expiration;private $termination;private $read;
    public function __construct(ManualCombatExpirationService $expiration,ManualCombatTerminationService $termination,ManualCombatReadService $read){$this->expiration=$expiration;$this->termination=$termination;$this->read=$read;}
    public function abandon(User $user,Character $character,CombatSession $combat,array $input)
    {
        return DB::transaction(function()use($user,$character,$combat,$input){
            $lockedCharacter=Character::whereKey($character->id)->lockForUpdate()->firstOrFail();if((int)$lockedCharacter->user_id!==(int)$user->id)throw new AuthorizationException();
            $lockedCombat=CombatSession::whereKey($combat->id)->lockForUpdate()->firstOrFail();if((int)$lockedCombat->owner_user_id!==(int)$user->id||(int)$lockedCombat->character_id!==(int)$lockedCharacter->id)throw new AuthorizationException();
            $this->expiration->expireIfNeededLocked($lockedCombat);
            $existing=CombatLifecycleRequest::where('combat_session_id',$lockedCombat->id)->where('client_request_id',$input['client_request_id'])->lockForUpdate()->first();
            if($existing){if($existing->status===ManualCombatLifecycleRequestStatus::PROCESSED&&is_array($existing->response_payload)){$payload=$existing->response_payload;$payload['idempotent_replay']=true;return new ManualCombatLifecycleResult($payload);}throw new ManualCombatConflictException('The abandon request is still processing.');}
            if($lockedCombat->status===ManualCombatStatus::ABANDONED||$lockedCombat->status===ManualCombatStatus::EXPIRED){$state=$this->read->read($user,$lockedCombat)->toArray();return new ManualCombatLifecycleResult(['idempotent_replay'=>true,'combat'=>$state]);}
            if(in_array($lockedCombat->status,[ManualCombatStatus::WON,ManualCombatStatus::LOST],true))throw new ManualCombatConflictException('A completed combat cannot be abandoned.');
            if((int)$input['expected_lock_version']!==(int)$lockedCombat->lock_version)throw new ManualCombatConflictException('The combat state version is stale.');
            $before=(int)$lockedCombat->lock_version;$request=CombatLifecycleRequest::create(['combat_session_id'=>$lockedCombat->id,'client_request_id'=>$input['client_request_id'],'request_type'=>ManualCombatLifecycleRequestType::ABANDON,'expected_lock_version'=>(int)$input['expected_lock_version'],'lock_version_before'=>$before,'status'=>ManualCombatLifecycleRequestStatus::PROCESSING]);
            $this->termination->abandonLocked($lockedCombat,CarbonImmutable::now());
            $payload=['idempotent_replay'=>false,'combat'=>$this->read->read($user,$lockedCombat)->toArray()];
            $request->status=ManualCombatLifecycleRequestStatus::PROCESSED;$request->lock_version_after=(int)$lockedCombat->lock_version;$request->response_payload=$payload;$request->processed_at=CarbonImmutable::now();$request->save();return new ManualCombatLifecycleResult($payload);
        },3);
    }
}
