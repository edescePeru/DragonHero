<?php

namespace App\Domain\Inventory\Instances;

use App\Models\Item;
use App\Models\ItemRarity;
use InvalidArgumentException;

final class ItemInstanceRarityResolver
{
    public function resolve(Item $item, $rarity = null)
    {
        $query = $item->allowedRarities()->orderBy('item_rarities.id');
        if ($rarity instanceof ItemRarity) {
            $resolved = $query->whereKey($rarity->id)->first();
        } elseif (is_int($rarity) || (is_string($rarity) && ctype_digit($rarity))) {
            $resolved = $query->whereKey((int) $rarity)->first();
        } elseif (is_string($rarity) && $rarity !== '') {
            ItemRarityCode::assert($rarity);
            $resolved = $query->where('code', $rarity)->first();
        } elseif ($rarity === null) {
            $resolved = $query->where('code', ItemRarityCode::COMMON)->first();
            if (!$resolved) {
                throw new InvalidArgumentException('Item rarity is required because common is not allowed.');
            }
        } else {
            throw new InvalidArgumentException('Invalid Item rarity selection.');
        }
        if (!$resolved) {
            throw new InvalidArgumentException('Selected rarity is not allowed for this Item.');
        }
        return $resolved;
    }
}
