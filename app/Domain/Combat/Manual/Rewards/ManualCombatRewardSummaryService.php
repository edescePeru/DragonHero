<?php
namespace App\Domain\Combat\Manual\Rewards;
use App\Models\CombatPendingReward;
use App\Models\CombatPendingRewardItem;
use App\Models\CombatSession;
use InvalidArgumentException;
final class ManualCombatRewardSummaryService
{
    public function forCombat(CombatSession $combat)
    {
        $rewards = $combat->relationLoaded('pendingRewards') ? $combat->pendingRewards : CombatPendingReward::where('combat_session_id', $combat->id)->orderBy('id')->get();
        $lines = $rewards->isEmpty() ? collect() : CombatPendingRewardItem::whereIn('combat_pending_reward_id', $rewards->pluck('id'))->orderBy('id')->get();
        return $this->fromLoaded($combat, $rewards, $lines);
    }
    public function fromLoaded(CombatSession $combat, $rewards, $lines)
    {
        $gold = 0; $experience = 0; $items = [];
        foreach ($rewards as $reward) { $gold = $this->add($gold, $reward->gold_amount); $experience = $this->add($experience, $reward->experience_amount); }
        foreach ($lines as $line) { $id = (int) $line->item_id; if (!isset($items[$id])) $items[$id] = ['item_id' => $id, 'name' => (string) $line->item_name_snapshot, 'quantity' => 0]; $items[$id]['quantity'] = $this->add($items[$id]['quantity'], $line->quantity); }
        $statuses = $rewards->pluck('status')->unique()->values();
        $status = 'none';
        if ($rewards->isNotEmpty()) { if ($statuses->count() !== 1) throw new InvalidArgumentException('Combat rewards have inconsistent statuses.'); $status = $statuses->first(); }
        return ['status' => $status, 'experience' => $experience, 'gold' => $gold, 'items' => array_values($items), 'claim_available' => $status === CombatPendingRewardStatus::PENDING_CLAIM && $combat->rewards_granted_at === null];
    }
    private function add($current, $value) { $value = (int) $value; if ($value < 0 || $value > PHP_INT_MAX - $current) throw new InvalidArgumentException('Combat reward value overflow.'); return $current + $value; }
}
