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
    private $classification;

    public function __construct(ItemClassification $classification)
    {
        $this->classification = $classification;
    }
    /** Internal authoritative batch API. Requires an active transaction and prelocked Character, Items and CharacterItems. */
    public function addManyLocked(Character $character, Collection $items, array $quantities, Collection $existing): Collection
    {
        if (DB::transactionLevel() < 1) throw new \RuntimeException('Active transaction required.');
        $entries=$existing->keyBy('item_id');if($entries->count()!==$existing->count()||$items->keyBy('id')->count()!==$items->count())throw new InvalidArgumentException('Duplicate inventory batch state.');$result=collect();
        foreach($quantities as $itemId=>$quantity){
            if(!is_int($itemId)||$itemId<=0||!is_int($quantity)||$quantity<=0)throw new InvalidArgumentException('Invalid normalized inventory batch.');
            $item=$items->get($itemId);if(!$item||((int)$item->id)!==$itemId||$this->classification->classify($item)!==ItemClassification::STACKABLE)throw new InvalidArgumentException('Inventory batch Item mismatch.');
            $entry=$entries->get($itemId);if($entry&&((int)$entry->character_id!==(int)$character->id||(int)$entry->item_id!==$itemId))throw new InvalidArgumentException('Inventory batch entry mismatch.');
            if(!$entry)$entry=new CharacterItem(['character_id'=>$character->id,'item_id'=>$itemId,'quantity'=>0,'locked_quantity'=>0]);
            $current=(int)$entry->quantity;if($quantity>PHP_INT_MAX-$current)throw new InvalidArgumentException('Inventory quantity overflow.');$entry->quantity=$current+$quantity;$this->assertInvariants($entry);$entry->save();$result->push($this->toEntry($entry,$item));
        }
        return$result;
    }
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
        if ($this->classification->classify($item) !== ItemClassification::STACKABLE || $item->status !== CatalogStatus::ACTIVE) {
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
