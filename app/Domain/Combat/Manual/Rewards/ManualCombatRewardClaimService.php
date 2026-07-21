<?php

namespace App\Domain\Combat\Manual\Rewards;

use App\Domain\Characters\Progression\CharacterProgressionService;
use App\Domain\Combat\Manual\ManualCombatEventService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Combat\Manual\Rewards\Data\CombatRewardClaimResult;
use App\Domain\Combat\Manual\Rewards\Exceptions\CombatRewardDeliveryUnavailableException;
use App\Domain\Inventory\Capacity\PendingRewardCapacityService;
use App\Domain\Inventory\Instances\ItemInstanceService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\ItemClassification;
use App\Domain\Support\DragonHeroUuid;
use App\Domain\Wallet\GoldReasonCode;
use App\Domain\Wallet\WalletService;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CombatPendingReward;
use App\Models\CombatPendingRewardItem;
use App\Models\CombatSession;
use App\Models\Item;
use App\Models\ItemInstance;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use App\Domain\Combat\Manual\ManualCombatExpirationService;

final class ManualCombatRewardClaimService
{
    private $inventory; private $capacity; private $wallet; private $progression; private $instances; private $classification; private $events; private $summary; private $expiration;
    public function __construct(InventoryService $inventory, PendingRewardCapacityService $capacity, WalletService $wallet, CharacterProgressionService $progression, ItemInstanceService $instances, ItemClassification $classification, ManualCombatEventService $events, ManualCombatRewardSummaryService $summary,ManualCombatExpirationService $expiration)
    { $this->inventory=$inventory; $this->capacity=$capacity; $this->wallet=$wallet; $this->progression=$progression; $this->instances=$instances; $this->classification=$classification; $this->events=$events; $this->summary=$summary; $this->expiration=$expiration; }

    public function claim(Character $character, CombatSession $combat)
    {
        $result=DB::transaction(function () use ($character, $combat) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $lockedCombat = CombatSession::whereKey($combat->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedCombat->character_id !== (int) $lockedCharacter->id) throw new AuthorizationException();
            if($this->expiration->expireIfNeededLocked($lockedCombat))return '__manual_combat_expired__';
            return $this->claimLocked($lockedCharacter, $lockedCombat);
        }, 3);
        if($result==='__manual_combat_expired__')throw new InvalidArgumentException('The combat has expired.');
        return$result;
    }

    public function claimLocked(Character $character, CombatSession $combat)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if ((int) $combat->character_id !== (int) $character->id) throw new AuthorizationException();
        $rewards = CombatPendingReward::where('combat_session_id', $combat->id)->orderBy('id')->lockForUpdate()->get();
        $lines = $rewards->isEmpty() ? collect() : CombatPendingRewardItem::whereIn('combat_pending_reward_id', $rewards->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        if ($combat->rewards_granted_at !== null) return new CombatRewardClaimResult(['idempotent_replay' => true, 'rewards' => $this->summary->fromLoaded($combat, $rewards, $lines)]);
        if ($combat->status !== ManualCombatStatus::WON || $rewards->isEmpty()) throw new InvalidArgumentException('Combat rewards are not claimable.');
        foreach ($rewards as $reward) if (!in_array($reward->status, CombatPendingRewardStatus::pendingValues(), true)) throw new InvalidArgumentException('Combat reward state is not claimable.');

        $now = CarbonImmutable::now();
        $inventory = CharacterItem::where('character_id', $character->id)->orderBy('id')->lockForUpdate()->get();
        $ownedInstances = ItemInstance::where('character_id', $character->id)->orderBy('id')->lockForUpdate()->get();
        $itemIds = $inventory->pluck('item_id')->merge($ownedInstances->pluck('item_id'))->merge($lines->pluck('item_id'))->unique()->sort()->values();
        $items = $itemIds->isEmpty() ? collect() : Item::whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($items->count() !== $itemIds->count()) throw new RuntimeException('Inventory or combat reward references a missing Item.');
        list($stackable, $uniqueLines) = $this->planItems($lines, $items);
        $capacity = $this->capacity->fromLoadedState($character, $inventory, $lines, $items, $now, $ownedInstances);
        if (!$capacity->claimFits()) throw new CombatRewardDeliveryUnavailableException($capacity);

        $operationUuid = DragonHeroUuid::versionFive('manual-combat-reward-claim:v1:'.$character->id.':'.$combat->id);
        if (!empty($stackable)) $this->inventory->addManyLocked($character, $items, $stackable, $inventory);
        foreach ($uniqueLines as $line) $this->instances->createFromCombatRewardLocked($character, $line, $items->get($line->item_id), $operationUuid, $now);
        list($gold, $experience) = $this->aggregateValues($rewards);
        if ($gold > 0) $this->wallet->creditLocked($character, $gold, GoldReasonCode::HUNTING_REWARD, 'Recompensa agregada de combate manual', 'combat_session', (int) $combat->id, $operationUuid);
        $this->progression->grantExperienceLocked($character, $experience);
        foreach ($rewards as $reward) { $reward->status = CombatPendingRewardStatus::GRANTED; $reward->granted_at = $now; $reward->save(); }
        $combat->rewards_granted_at = $now; $combat->save();
        $summary = $this->summary->fromLoaded($combat, $rewards, $lines); $summary['status'] = CombatPendingRewardStatus::GRANTED; $summary['claim_available'] = false;
        $this->events->append($combat, ManualCombatEventType::REWARDS_GRANTED, (int) $combat->round_number, null, ['version' => 1, 'reward' => $summary]);
        return new CombatRewardClaimResult(['idempotent_replay' => false, 'rewards' => $summary]);
    }

    private function planItems($lines, $items)
    {
        $stackable=[]; $unique=[];
        foreach ($lines as $line) { $quantity=$this->positive($line->quantity); $item=$items->get($line->item_id); if (!$item) throw new RuntimeException('Combat reward Item is missing.'); $kind=$this->classification->classify($item); if ($kind===ItemClassification::STACKABLE) { $old=isset($stackable[$item->id])?$stackable[$item->id]:0; if ($quantity>PHP_INT_MAX-$old) throw new InvalidArgumentException('Combat reward quantity overflow.'); $stackable[$item->id]=$old+$quantity; } else $unique[]=$line; }
        ksort($stackable, SORT_NUMERIC); return [$stackable, $unique];
    }
    private function aggregateValues($rewards) { $gold=0; $experience=0; foreach ($rewards as $reward) { $g=(int)$reward->gold_amount; $e=(int)$reward->experience_amount; if ($g<0||$e<0||$g>PHP_INT_MAX-$gold||$e>PHP_INT_MAX-$experience) throw new InvalidArgumentException('Combat reward value overflow.'); $gold+=$g; $experience+=$e; } return [$gold,$experience]; }
    private function positive($value) { $value=(int)$value; if ($value<=0) throw new InvalidArgumentException('Invalid combat reward quantity.'); return $value; }
}
