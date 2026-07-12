<?php

namespace App\Domain\Inventory;

use App\Domain\Inventory\Data\InventoryEntry;
use App\Domain\Inventory\Exceptions\InsufficientItemQuantityException;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryService
{
    public function addItem(Character $character, Item $item, int $quantity): InventoryEntry
    {
        $this->assertPositive($quantity);
        $this->assertUsableItem($item);

        return $this->write($character, $item, function ($entry, $lockedItem) use ($character, $quantity) {
            if (! $entry) {
                $entry = new CharacterItem(['character_id' => $character->id, 'item_id' => $lockedItem->id, 'quantity' => 0, 'locked_quantity' => 0]);
            }
            $entry->quantity = (int) $entry->quantity + $quantity;
            $this->assertInvariants($entry);
            $entry->save();
            return $this->toEntry($entry, $lockedItem);
        });
    }

    public function removeItem(Character $character, Item $item, int $quantity): ?InventoryEntry
    {
        $this->assertPositive($quantity);
        $this->assertUsableItem($item);

        return $this->write($character, $item, function ($entry, $lockedItem) use ($quantity) {
            $available = $entry ? (int) $entry->quantity - (int) $entry->locked_quantity : 0;
            if ($available < $quantity) {
                throw new InsufficientItemQuantityException(InsufficientItemQuantityException::INSUFFICIENT_AVAILABLE_QUANTITY);
            }
            $entry->quantity -= $quantity;
            $this->assertInvariants($entry);
            if ((int) $entry->quantity === 0) {
                $entry->delete();
                return null;
            }
            $entry->save();
            return $this->toEntry($entry, $lockedItem);
        });
    }

    public function lockItemQuantity(Character $character, Item $item, int $quantity): InventoryEntry
    {
        $this->assertPositive($quantity);
        $this->assertUsableItem($item);
        return $this->write($character, $item, function ($entry, $lockedItem) use ($quantity) {
            $available = $entry ? (int) $entry->quantity - (int) $entry->locked_quantity : 0;
            if ($available < $quantity) {
                throw new InsufficientItemQuantityException(InsufficientItemQuantityException::INSUFFICIENT_AVAILABLE_QUANTITY);
            }
            $entry->locked_quantity += $quantity;
            $this->assertInvariants($entry);
            $entry->save();
            return $this->toEntry($entry, $lockedItem);
        });
    }

    public function unlockItemQuantity(Character $character, Item $item, int $quantity): InventoryEntry
    {
        $this->assertPositive($quantity);
        $this->assertUsableItem($item);
        return $this->write($character, $item, function ($entry, $lockedItem) use ($quantity) {
            if (! $entry || (int) $entry->locked_quantity < $quantity) {
                throw new InsufficientItemQuantityException(InsufficientItemQuantityException::INSUFFICIENT_LOCKED_QUANTITY);
            }
            $entry->locked_quantity -= $quantity;
            $this->assertInvariants($entry);
            $entry->save();
            return $this->toEntry($entry, $lockedItem);
        });
    }

    public function availableQuantity(Character $character, Item $item): int
    {
        $entry = CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->first();
        return $entry ? (int) $entry->quantity - (int) $entry->locked_quantity : 0;
    }

    public function hasItem(Character $character, Item $item, int $quantity = 1): bool
    {
        $this->assertPositive($quantity);
        return $this->availableQuantity($character, $item) >= $quantity;
    }

    /** @return Collection|InventoryEntry[] */
    public function entries(Character $character): Collection
    {
        return CharacterItem::query()->where('character_id', $character->id)->with('item')->orderBy('item_id')->get()
            ->map(function ($entry) { return $this->toEntry($entry, $entry->item); });
    }

    private function write(Character $character, Item $item, $operation)
    {
        return DB::transaction(function () use ($character, $item, $operation) {
            Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $lockedItem = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->assertUsableItem($lockedItem);
            $entry = CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->lockForUpdate()->first();
            return $operation($entry, $lockedItem);
        }, 3);
    }

    private function assertPositive($quantity)
    {
        if (! is_int($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be a positive integer.');
        }
    }

    private function assertUsableItem(Item $item)
    {
        if (! $item->is_stackable || $item->status !== CatalogStatus::ACTIVE) {
            throw new InvalidArgumentException('Only active stackable items are supported.');
        }
    }

    private function assertInvariants(CharacterItem $entry)
    {
        if ((int) $entry->quantity < 0 || (int) $entry->locked_quantity < 0 || (int) $entry->locked_quantity > (int) $entry->quantity) {
            throw new InvalidArgumentException('Inventory quantity invariant violated.');
        }
    }

    private function toEntry(CharacterItem $entry, Item $item): InventoryEntry
    {
        return new InventoryEntry($item->id, $item->code, $item->name, $item->item_type, $item->rarity, $entry->quantity, $entry->locked_quantity);
    }
}
