<?php

namespace App\Domain\Inventory\Capacity;

use App\Domain\Inventory\Instances\ItemInstanceInventoryPolicy;
use App\Domain\Inventory\ItemClassification;
use App\Models\Character;
use App\Models\Item;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class InventoryCapacityProjectionService
{
    private $slots;
    private $capacity;
    private $classification;
    private $instancePolicy;

    public function __construct(
        InventorySlotCalculator $slots,
        InventoryCapacityCalculator $capacity,
        ItemClassification $classification,
        ItemInstanceInventoryPolicy $instancePolicy
    ) {
        $this->slots = $slots;
        $this->capacity = $capacity;
        $this->classification = $classification;
        $this->instancePolicy = $instancePolicy;
    }

    /**
     * Projects an authoritative delivery over already loaded inventory state.
     * Delivery lines contain item_id and quantity and may represent stackable or unique Items.
     */
    public function fromLoadedState(Character $character, $inventory, $deliveryLines, $items, CarbonImmutable $now, $instances = null)
    {
        $instances = $instances ?: collect();
        list($effective, $permanent, $temporary) = $this->capacity->effective($character, $now);

        return $this->fromLoadedStateWithCapacity($character, $inventory, $deliveryLines, $items, $instances, $effective, $permanent, $temporary);
    }

    public function effectiveCapacity(Character $character, CarbonImmutable $now)
    {
        return $this->capacity->effective($character, $now);
    }

    public function fromLoadedStateWithCapacity(Character $character, $inventory, $deliveryLines, $items, $instances, $effective, $permanent, $temporary)
    {
        $instances = $instances ?: collect();

        $allIds = $inventory->pluck('item_id')
            ->merge($deliveryLines->pluck('item_id'))
            ->merge($instances->pluck('item_id'))
            ->unique();
        $missing = $allIds->filter(function ($id) use ($items) {
            return ! $items->has($id);
        })->values();
        if ($missing->isNotEmpty()) {
            $items = $items->union(Item::whereIn('id', $missing)->get()->keyBy('id'));
        }

        $current = [];
        $projected = [];
        foreach ($inventory as $entry) {
            $item = $items->get($entry->item_id);
            if (! $item || $this->classification->classify($item) !== ItemClassification::STACKABLE) {
                throw new InvalidArgumentException('Aggregated inventory requires coherent stackable Items.');
            }
            $line = $this->stackableLine($entry->item_id, $entry->quantity, $item);
            $current[] = $line;
            $projected[] = $line;
        }

        $instanceSlots = 0;
        foreach ($instances as $instance) {
            $item = $items->get($instance->item_id);
            if (! $item || $this->classification->classify($item) !== ItemClassification::UNIQUE) {
                throw new InvalidArgumentException('ItemInstance requires a coherent unique Item.');
            }
            if ($this->instancePolicy->occupiesInventory($instance->status)) {
                $instanceSlots++;
            }
        }

        $projectedUnique = 0;
        foreach ($deliveryLines as $entry) {
            $item = $items->get($entry->item_id);
            if (! $item) {
                throw new InvalidArgumentException('Projected delivery Item is missing.');
            }
            $quantity = $this->safeInteger($entry->quantity);
            if ($this->classification->classify($item) === ItemClassification::STACKABLE) {
                $projected[] = $this->stackableLine($entry->item_id, $quantity, $item);
            } else {
                if ($quantity > PHP_INT_MAX - $projectedUnique) {
                    throw new InvalidArgumentException('Projected unique Item overflow.');
                }
                $projectedUnique += $quantity;
            }
        }

        $currentSlots = $this->slots->calculate($current, $instanceSlots)->slots();
        $projectedSlots = $this->slots->calculate($projected, $instanceSlots + $projectedUnique)->slots();

        return $this->capacity->result($effective, $permanent, $temporary, $currentSlots, $projectedSlots);
    }

    private function stackableLine($itemId, $quantity, Item $item)
    {
        return [
            'item_id' => (int) $itemId,
            'quantity' => $this->safeInteger($quantity),
            'max_stack' => $this->safeInteger($item->max_stack),
        ];
    }

    private function safeInteger($value)
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]*)$/', $value)
            || strlen($value) > strlen((string) PHP_INT_MAX)
            || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('Quantity exceeds safe PHP integer range.');
        }

        return (int) $value;
    }
}
