<?php
namespace App\Domain\Combat\Manual\Rewards;
use App\Domain\Combat\Manual\ManualCombatEventService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Combat\Manual\Rewards\Exceptions\CombatRewardDeliveryUnavailableException;
use App\Models\Character;
use App\Models\CombatPendingReward;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
final class ManualCombatRewardLifecycleService
{
    private $claim; private $events; private $forfeit;
    public function __construct(ManualCombatRewardClaimService $claim, ManualCombatEventService $events, ManualCombatRewardForfeitService $forfeit) { $this->claim=$claim; $this->events=$events; $this->forfeit=$forfeit; }
    public function victoryLocked(Character $character, CombatSession $combat)
    {
        try { return $this->claim->claimLocked($character, $combat); }
        catch (CombatRewardDeliveryUnavailableException $exception) {
            $now=CarbonImmutable::now();
            $rewards=CombatPendingReward::where('combat_session_id',$combat->id)->where('status',CombatPendingRewardStatus::PENDING)->orderBy('id')->lockForUpdate()->get();
            foreach($rewards as $reward){$reward->status=CombatPendingRewardStatus::PENDING_CLAIM;$reward->save();}
            $this->events->append($combat,ManualCombatEventType::REWARDS_PENDING_CLAIM,(int)$combat->round_number,null,['version'=>1,'reason'=>'insufficient_inventory_capacity','inventory_capacity'=>$exception->capacity()->toArray()]);
            return null;
        }
    }
    public function defeatLocked(CombatSession $combat)
    {
        return $this->forfeit->forfeitLocked($combat,'lost',CarbonImmutable::now());
    }
}
