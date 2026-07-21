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
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ItemInstanceService
{
    private $classification;
    public function __construct(ItemClassification $classification) { $this->classification = $classification; }

    public function createFromRewardLocked(Character $character, HuntRewardItem $line, Item $item, string $operationUuid, CarbonImmutable $now): Collection
    {
        return $this->createLocked($character, $line, $item, $operationUuid, $now, ItemInstanceOriginType::HUNT_REWARD_ITEM, ItemInstanceEventType::CREATED_FROM_HUNT_REWARD, 'hunt-reward-item-instance:v1:');
    }

    public function createFromCombatRewardLocked(Character $character, CombatPendingRewardItem $line, Item $item, string $operationUuid, CarbonImmutable $now): Collection
    {
        return $this->createLocked($character, $line, $item, $operationUuid, $now, ItemInstanceOriginType::COMBAT_PENDING_REWARD_ITEM, ItemInstanceEventType::CREATED_FROM_COMBAT_REWARD, 'combat-reward-item-instance:v1:');
    }

    private function createLocked(Character $character, $line, Item $item, string $operationUuid, CarbonImmutable $now, $originType, $eventType, $uuidPrefix)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if ((int) $line->item_id !== (int) $item->id || $this->classification->classify($item) !== ItemClassification::UNIQUE) throw new InvalidArgumentException('Reward line requires a coherent unique Item.');
        $quantity = $this->positive($line->quantity); $results = collect();
        for ($unit = 1; $unit <= $quantity; $unit++) {
            $existing = ItemInstance::where('origin_type', $originType)->where('origin_id', $line->id)->where('origin_unit_index', $unit)->lockForUpdate()->first();
            if ($existing) { $this->assertCompatibleReplay($existing, $character, $line, $item, $unit, $operationUuid, $originType, $eventType); $results->push($this->entry($existing, $item)); continue; }
            $instance = ItemInstance::create(['uuid' => DragonHeroUuid::versionFive($uuidPrefix.$line->id.':'.$unit), 'character_id' => $character->id, 'item_id' => $item->id, 'refinement_level' => 0, 'status' => ItemInstanceStatus::AVAILABLE, 'origin_type' => $originType, 'origin_id' => $line->id, 'origin_unit_index' => $unit, 'acquired_at' => $now]);
            ItemInstanceEvent::create(['item_instance_id' => $instance->id, 'operation_uuid' => $operationUuid, 'event_type' => $eventType, 'actor_character_id' => $character->id, 'from_character_id' => null, 'to_character_id' => $character->id, 'from_item_id' => null, 'to_item_id' => $item->id, 'refinement_before' => null, 'refinement_after' => 0, 'source_type' => $originType, 'source_id' => $line->id, 'metadata' => null, 'occurred_at' => $now, 'created_at' => $now]);
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
    private function entry(ItemInstance $instance, Item $item) { return new ItemInstanceEntry($instance->uuid, (int) $item->id, $item->code, $item->name, (int) $instance->refinement_level, $instance->status, $instance->acquired_at->toIso8601String()); }
}
