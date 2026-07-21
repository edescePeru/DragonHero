<?php
namespace App\Domain\Combat\Manual;
use App\Domain\Combat\Manual\Rewards\ManualCombatRewardForfeitService;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
final class ManualCombatTerminationService
{
    private $events;private $forfeit;
    public function __construct(ManualCombatEventService $events,ManualCombatRewardForfeitService $forfeit){$this->events=$events;$this->forfeit=$forfeit;}
    public function abandonLocked(CombatSession $combat,CarbonImmutable $now)
    {return$this->terminateLocked($combat,ManualCombatStatus::ABANDONED,ManualCombatEventType::COMBAT_ABANDONED,'player_abandoned','abandoned',$now,[]);}
    public function expireLocked(CombatSession $combat,CarbonImmutable $now,$minutes)
    {return$this->terminateLocked($combat,ManualCombatStatus::EXPIRED,ManualCombatEventType::COMBAT_EXPIRED,'inactivity_timeout','expired',$now,['expired_at'=>$now->toIso8601String(),'expiration_minutes'=>(int)$minutes]);}
    private function terminateLocked(CombatSession $combat,$status,$eventType,$eventReason,$forfeitReason,CarbonImmutable $now,array $extra)
    {
        if(DB::transactionLevel()<1)throw new RuntimeException('Active transaction required.');
        if(!in_array($combat->status,[ManualCombatStatus::ACTIVE,ManualCombatStatus::WAITING_PLAYER],true))throw new InvalidArgumentException('Combat state does not allow this terminal transition.');
        $combat->status=$status;$combat->current_participant_id=null;$combat->active_slot=null;$combat->completed_at=$now;$combat->last_action_at=$now;$combat->lock_version=(int)$combat->lock_version+1;$combat->save();
        $payload=array_merge(['version'=>1,'reason'=>$eventReason,'status'=>$status,'round'=>(int)$combat->round_number],$extra);
        $this->events->append($combat,$eventType,(int)$combat->round_number,null,$payload);
        $this->forfeit->forfeitLocked($combat,$forfeitReason,$now);
        return$combat;
    }
}
