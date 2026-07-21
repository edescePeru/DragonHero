<?php
namespace App\Domain\Combat\Manual\Rewards;
use App\Domain\Combat\Manual\ManualCombatEventService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Models\CombatPendingReward;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class ManualCombatRewardForfeitService
{
    private $events;
    public function __construct(ManualCombatEventService $events){$this->events=$events;}
    public function forfeitLocked(CombatSession $combat,$reason,CarbonImmutable $now)
    {
        if(DB::transactionLevel()<1)throw new RuntimeException('Active transaction required.');
        $rewards=CombatPendingReward::where('combat_session_id',$combat->id)->whereIn('status',CombatPendingRewardStatus::pendingValues())->orderBy('id')->lockForUpdate()->get();
        if($rewards->isEmpty())return 0;
        foreach($rewards as $reward){$reward->status=CombatPendingRewardStatus::FORFEITED;$reward->forfeited_at=$now;$reward->save();}
        $this->events->append($combat,ManualCombatEventType::REWARDS_FORFEITED,(int)$combat->round_number,null,['version'=>1,'reason'=>(string)$reason,'rewards_count'=>$rewards->count()]);
        return$rewards->count();
    }
}
