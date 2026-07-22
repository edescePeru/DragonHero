<?php

namespace App\Domain\Inventory\Capacity;

use App\Domain\Hunts\Rewards\HuntRewardStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\HuntReward;
use App\Models\HuntRewardItem;
use App\Models\Item;
use App\Models\ItemInstance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PendingRewardCapacityService
{
    private $projection;

    public function __construct(InventoryCapacityProjectionService $projection)
    {
        $this->projection = $projection;
    }

    public function snapshot(Character $character, CarbonImmutable $now = null)
    {
        return $this->calculate($character, $now ?: CarbonImmutable::now(), false);
    }

    public function locked(Character $character, CarbonImmutable $now)
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('Active transaction required.');
        }

        return $this->calculate($character, $now, true);
    }

    public function fromLoadedState(Character $character, $inventory, $rewardItems, $items, CarbonImmutable $now, $instances = null)
    {
        return $this->projection->fromLoadedState($character, $inventory, $rewardItems, $items, $now, $instances);
    }

    private function calculate(Character $character, CarbonImmutable $now, $lock)
    {
        $inventoryQuery = CharacterItem::where('character_id', $character->id)->orderBy('id');
        if ($lock) {
            $inventoryQuery->lockForUpdate();
        }
        $inventory = $inventoryQuery->get();

        $instancesQuery = ItemInstance::where('character_id', $character->id)->orderBy('id');
        if ($lock) {
            $instancesQuery->lockForUpdate();
        }
        $instances = $instancesQuery->get();

        $rewardQuery = HuntReward::query()->select('hunt_rewards.*')
            ->join('hunting_sessions', 'hunting_sessions.id', '=', 'hunt_rewards.hunting_session_id')
            ->where('hunting_sessions.character_id', $character->id)
            ->where('hunt_rewards.status', HuntRewardStatus::PENDING)
            ->orderBy('hunt_rewards.id');
        if ($lock) {
            $rewardQuery->lockForUpdate();
        }
        $rewards = $rewardQuery->get();
        $rewardItems = $rewards->isEmpty() ? collect() : HuntRewardItem::whereIn('hunt_reward_id', $rewards->pluck('id'))
            ->orderBy('id')->when($lock, function ($query) {
                $query->lockForUpdate();
            })->get();

        $itemIds = $inventory->pluck('item_id')->merge($rewardItems->pluck('item_id'))
            ->merge($instances->pluck('item_id'))->unique()->values();
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        return $this->projection->fromLoadedState($character, $inventory, $rewardItems, $items, $now, $instances);
    }
}
