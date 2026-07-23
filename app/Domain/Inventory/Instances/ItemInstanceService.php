<?php
namespace App\Domain\Inventory\Instances;
use App\Domain\Inventory\Instances\Data\ItemInstanceEntry;
use App\Domain\Inventory\ItemClassification;
use App\Domain\Support\DragonHeroUuid;
use App\Models\Character;
use App\Models\CombatPendingRewardItem;
use App\Models\HuntRewardItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\ItemInstanceEvent;
use App\Models\ItemRarity;
use App\Models\ShopPurchase;
use App\Models\ShopSale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ItemInstanceService
{
    private $classification;
    private $events;
    private $rarities;
    public function __construct(ItemClassification $classification, ItemInstanceEventService $events, ItemInstanceRarityResolver $rarities) { $this->classification = $classification; $this->events = $events; $this->rarities = $rarities; }

    public function createFromRewardLocked(Character $character, HuntRewardItem $line, Item $item, string $operationUuid, CarbonImmutable $now, $rarity = null): Collection
    {
        return $this->createLocked($character, $line, $item, $operationUuid, $now, ItemInstanceOriginType::HUNT_REWARD_ITEM, ItemInstanceEventType::CREATED_FROM_HUNT_REWARD, 'hunt-reward-item-instance:v1:', $rarity);
    }

    public function createFromCombatRewardLocked(Character $character, CombatPendingRewardItem $line, Item $item, string $operationUuid, CarbonImmutable $now, $rarity = null): Collection
    {
        return $this->createLocked($character, $line, $item, $operationUuid, $now, ItemInstanceOriginType::COMBAT_PENDING_REWARD_ITEM, ItemInstanceEventType::CREATED_FROM_COMBAT_REWARD, 'combat-reward-item-instance:v1:', $rarity);
    }

    public function createFromShopPurchaseLocked(Character $character, Item $item, ShopPurchase $purchase, int $quantity, CarbonImmutable $now, $rarity = null): Collection
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if ((int) $purchase->character_id !== (int) $character->id || (int) $purchase->item_id !== (int) $item->id || $this->classification->classify($item) !== ItemClassification::UNIQUE) throw new InvalidArgumentException('ShopPurchase requires a coherent unique Item.');
        $quantity = $this->positive($quantity);
        $resolvedRarity = $this->rarities->resolve($item, $rarity);
        $operationUuid = $purchase->idempotency_key;
        $results = collect();
        for ($unit = 1; $unit <= $quantity; $unit++) {
            $instance = ItemInstance::create([
                'uuid' => DragonHeroUuid::versionFive('shop-purchase-item-instance:v1:'.$purchase->id.':'.$unit),
                'character_id' => $character->id,
                'item_id' => $item->id,
                'item_rarity_id' => $resolvedRarity->id,
                'refinement_level' => 0,
                'status' => ItemInstanceStatus::AVAILABLE,
                'origin_type' => ItemInstanceOriginType::SHOP_PURCHASE,
                'origin_id' => $purchase->id,
                'origin_unit_index' => $unit,
                'acquired_at' => $now,
            ]);
            ItemInstanceEvent::create([
                'item_instance_id' => $instance->id,
                'operation_uuid' => $operationUuid,
                'event_type' => ItemInstanceEventType::CREATED_FROM_SHOP_PURCHASE,
                'actor_character_id' => $character->id,
                'from_character_id' => null,
                'to_character_id' => $character->id,
                'from_item_id' => null,
                'to_item_id' => $item->id,
                'refinement_before' => null,
                'refinement_after' => 0,
                'source_type' => ItemInstanceOriginType::SHOP_PURCHASE,
                'source_id' => $purchase->id,
                'metadata' => $this->rarityMetadata($resolvedRarity),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);
            $instance->setRelation('itemRarity', $resolvedRarity);
            $results->push($this->entry($instance, $item));
        }
        return $results;
    }

    public function markSoldToShopLocked(Character $character, ItemInstance $instance, ShopSale $sale, CarbonImmutable $now)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if (!$character->exists || !$instance->exists || !$sale->exists) throw new InvalidArgumentException('Persisted Shop sale context is required.');
        if ((int) $instance->character_id !== (int) $character->id || (int) $sale->character_id !== (int) $character->id || (int) $sale->item_instance_id !== (int) $instance->id || (int) $sale->item_id !== (int) $instance->item_id) throw new InvalidArgumentException('ShopSale ItemInstance context mismatch.');
        if (!$instance->isAvailable()) throw new InvalidArgumentException('Only an available ItemInstance can be sold.');
        if ($instance->equipment()->exists()) throw new InvalidArgumentException('Equipped ItemInstance must be unequipped before sale.');
        $before = $instance->status;
        $instance->status = ItemInstanceStatus::SOLD;
        $instance->save();
        $rarity=$instance->relationLoaded('itemRarity')?$instance->itemRarity:$instance->itemRarity()->first();
        $metadata = ['shop_sale_id'=>(int)$sale->id,'shop_id'=>(int)$sale->shop_id,'character_id'=>(int)$character->id,'item_id'=>(int)$instance->item_id,'item_instance_uuid'=>$instance->uuid,'rarity_id'=>$rarity?(int)$rarity->id:null,'rarity_code'=>$rarity?$rarity->code:ItemRarityCode::COMMON,'rarity_name'=>$rarity?$rarity->name:'Común','refinement_level'=>(int)$instance->refinement_level,'status_before'=>$before,'sold_at'=>$now->toIso8601String()];
        $this->events->appendSoldToShop($instance,$character,$sale,$metadata,$now);
        return $instance;
    }

    private function createLocked(Character $character, $line, Item $item, string $operationUuid, CarbonImmutable $now, $originType, $eventType, $uuidPrefix, $rarity)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if ((int) $line->item_id !== (int) $item->id || $this->classification->classify($item) !== ItemClassification::UNIQUE) throw new InvalidArgumentException('Reward line requires a coherent unique Item.');
        $quantity = $this->positive($line->quantity); $results = collect();
        $resolvedRarity = $this->resolveRewardRarity($line, $item, $rarity);
        for ($unit = 1; $unit <= $quantity; $unit++) {
            $existing = ItemInstance::where('origin_type', $originType)->where('origin_id', $line->id)->where('origin_unit_index', $unit)->lockForUpdate()->first();
            if ($existing) { $this->assertCompatibleReplay($existing, $character, $line, $item, $unit, $operationUuid, $originType, $eventType); $results->push($this->entry($existing, $item)); continue; }
            $instance = ItemInstance::create(['uuid' => DragonHeroUuid::versionFive($uuidPrefix.$line->id.':'.$unit), 'character_id' => $character->id, 'item_id' => $item->id, 'item_rarity_id' => $resolvedRarity->id, 'refinement_level' => 0, 'status' => ItemInstanceStatus::AVAILABLE, 'origin_type' => $originType, 'origin_id' => $line->id, 'origin_unit_index' => $unit, 'acquired_at' => $now]);
            ItemInstanceEvent::create(['item_instance_id' => $instance->id, 'operation_uuid' => $operationUuid, 'event_type' => $eventType, 'actor_character_id' => $character->id, 'from_character_id' => null, 'to_character_id' => $character->id, 'from_item_id' => null, 'to_item_id' => $item->id, 'refinement_before' => null, 'refinement_after' => 0, 'source_type' => $originType, 'source_id' => $line->id, 'metadata' => $this->rarityMetadata($resolvedRarity), 'occurred_at' => $now, 'created_at' => $now]);
            $instance->setRelation('itemRarity', $resolvedRarity);
            $results->push($this->entry($instance, $item));
        }
        return $results;
    }

    private function assertCompatibleReplay(ItemInstance $instance, Character $character, $line, Item $item, int $unit, string $operationUuid, $originType, $eventType)
    {
        $valid = (int) $instance->character_id === (int) $character->id && (int) $instance->item_id === (int) $item->id && $instance->origin_type === $originType && (int) $instance->origin_id === (int) $line->id && (int) $instance->origin_unit_index === $unit;
        $event = ItemInstanceEvent::where('item_instance_id', $instance->id)->where('event_type', $eventType)->where('source_type', $originType)->where('source_id', $line->id)->lockForUpdate()->first();
        $valid = $valid && $event && $event->operation_uuid === $operationUuid && (int) $event->to_character_id === (int) $character->id && (int) $event->to_item_id === (int) $item->id;
        if (!$valid) throw new RuntimeException('ItemInstance origin collision or incompatible replay.');
    }
    private function positive($value) { if (is_int($value) && $value > 0) return $value; if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) { $maximum = (string) PHP_INT_MAX; if (strlen($value) < strlen($maximum) || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) <= 0)) return (int) $value; } throw new InvalidArgumentException('Invalid unique reward quantity.'); }
    private function entry(ItemInstance $instance, Item $item) { $rarity=$instance->relationLoaded('itemRarity')?$instance->itemRarity:$instance->itemRarity()->firstOrFail();return new ItemInstanceEntry($instance->uuid, (int) $item->id, $item->code, $item->name, (int) $instance->refinement_level, $instance->status, $instance->acquired_at->toIso8601String(),(int)$rarity->id,$rarity->code,$rarity->name,$rarity->visual_style); }
    private function rarityMetadata($rarity){return['rarity_id'=>(int)$rarity->id,'rarity_code'=>$rarity->code,'rarity_name'=>$rarity->name];}
    private function resolveRewardRarity($line,Item $item,$rarity){if($rarity!==null)return$this->rarities->resolve($item,$rarity);if($line->item_rarity_id)return ItemRarity::whereKey($line->item_rarity_id)->firstOrFail();$allowed=$item->allowedRarities()->get();if($allowed->count()===1)return$allowed->first();$common=$allowed->firstWhere('code',ItemRarityCode::COMMON);if($common)return$common;throw new InvalidArgumentException('Historical unique reward has multiple rarities and no deterministic common fallback.');}
}
