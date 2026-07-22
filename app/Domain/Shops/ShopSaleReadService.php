<?php

namespace App\Domain\Shops;

use App\Domain\Inventory\Capacity\PendingRewardCapacityService;
use App\Domain\Inventory\ItemClassification;
use App\Domain\Media\MediaAssetType;
use App\Domain\Shops\Data\ShopSaleCatalogResult;
use App\Domain\Shops\Data\ShopSaleInventoryEntry;
use App\Domain\Wallet\WalletService;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\ItemInstance;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class ShopSaleReadService
{
    private $access;
    private $classification;
    private $items;
    private $pricing;
    private $rates;
    private $wallet;
    private $capacity;

    public function __construct(ShopAccessService $access, ItemClassification $classification, NpcSaleItemValidator $items, ShopSalePricingService $pricing, ShopPurchaseRate $rates, WalletService $wallet, PendingRewardCapacityService $capacity)
    {
        $this->access = $access;
        $this->classification = $classification;
        $this->items = $items;
        $this->pricing = $pricing;
        $this->rates = $rates;
        $this->wallet = $wallet;
        $this->capacity = $capacity;
    }

    public function catalog($user, $character, $shop, $zoneId = null)
    {
        if ((int) $character->user_id !== (int) $user->id) {
            throw new AuthorizationException();
        }

        $now = CarbonImmutable::now('UTC');
        $zone = $this->access->authorize($shop, $character, $zoneId, $now);
        $capacity = $this->capacity->snapshot($character, $now)->toArray();
        $base = [
            'shop_id' => (int) $shop->id,
            'shop_name' => $shop->name,
            'shop_can_buy' => (bool) $shop->buys_items,
            'purchase_rate_basis_points' => (int) $shop->purchase_rate_basis_points,
            'purchase_rate_percent' => $this->rates->toPercentage((int) $shop->purchase_rate_basis_points),
            'current_gold' => $this->wallet->balance($character)->balance(),
            'inventory_used' => $capacity['current_used_slots'],
            'inventory_capacity' => $capacity['effective_capacity'],
            'zone_id' => $zone,
            'generated_at' => $now->toIso8601String(),
        ];

        if (! $shop->buys_items) {
            return new ShopSaleCatalogResult(array_merge($base, ['entries' => [], 'sellable_count' => 0, 'blocked_count' => 0]));
        }

        $stacks = CharacterItem::where('character_id', $character->id)->orderBy('id')->get();
        $instances = ItemInstance::where('character_id', $character->id)->where('status', '<>', 'sold')->orderBy('id')->get();
        $equipment = CharacterEquipment::where('character_id', $character->id)->pluck('item_instance_id')->flip();
        $ids = $stacks->pluck('item_id')->merge($instances->pluck('item_id'))->unique();
        $catalog = Item::whereIn('id', $ids)->with(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::ICON)->where('is_primary', true)->orderBy('id');
        }])->get()->keyBy('id');
        $entries = [];

        foreach ($stacks as $row) {
            $entries[] = $this->entry($row, null, $catalog->get($row->item_id), $shop, $equipment);
        }
        foreach ($instances as $instance) {
            $entries[] = $this->entry(null, $instance, $catalog->get($instance->item_id), $shop, $equipment);
        }

        usort($entries, function ($left, $right) {
            $a = $left->toArray();
            $b = $right->toArray();
            return [! $a['can_sell'], $a['item_type'], $a['rarity'], $a['item_name'], $a['source_type'], $a['stable_id']] <=> [! $b['can_sell'], $b['item_type'], $b['rarity'], $b['item_name'], $b['source_type'], $b['stable_id']];
        });
        $sellable = count(array_filter($entries, function ($entry) {
            return $entry->canSell();
        }));

        return new ShopSaleCatalogResult(array_merge($base, ['entries' => $entries, 'sellable_count' => $sellable, 'blocked_count' => count($entries) - $sellable]));
    }

    private function entry($row, $instance, $item, $shop, $equipment)
    {
        $source = $row ? 'stack' : 'instance';
        $total = $row ? (int) $row->quantity : 1;
        $locked = $row ? (int) $row->locked_quantity : 0;
        $available = max(0, $total - $locked);
        $reason = null;
        $warning = $row && $locked > 0 && $available > 0 ? 'partially_locked' : null;
        $classification = null;
        $unit = null;
        $maximum = null;

        if (! $item) {
            $reason = 'invalid_classification';
        } else {
            try {
                $classification = $this->classification->classify($item);
            } catch (InvalidArgumentException $exception) {
                $reason = 'invalid_classification';
            }
            $expected = $row ? ItemClassification::STACKABLE : ItemClassification::UNIQUE;
            if (! $reason && $classification !== $expected) $reason = 'invalid_classification';
            if (! $reason && $item->status !== CatalogStatus::ACTIVE) $reason = 'item_inactive';
            if (! $reason) {
                $evaluation = $this->items->evaluate($item);
                if (! $evaluation->eligible()) $reason = $evaluation->reasonCode();
            }
            if (! $reason && $row && $available === 0) $reason = 'no_available_quantity';
            $equipped = $instance && ($instance->status === 'equipped' || $equipment->has($instance->id));
            if (! $reason && $instance && $equipped) $reason = 'equipped';
            if (! $reason && $instance && $instance->status !== 'available') $reason = 'unavailable_instance';
            if (! $reason) {
                try {
                    $price = $this->pricing->calculate((int) $item->sell_price, (int) $shop->purchase_rate_basis_points, $available);
                    $unit = $price->unitGold();
                    $maximum = $price->totalGold();
                } catch (InvalidArgumentException $exception) {
                    $reason = 'zero_sale_value';
                }
            }
        }

        $asset = $item && $item->mediaAssets->first() ? $item->mediaAssets->first()->url() : null;

        return new ShopSaleInventoryEntry([
            'source_type' => $source, 'character_item_id' => $row ? (int) $row->id : null,
            'item_instance_uuid' => $instance ? $instance->uuid : null, 'item_id' => $item ? (int) $item->id : null,
            'item_code' => $item ? $item->code : null, 'item_name' => $item ? $item->name : null,
            'item_type' => $item ? $item->item_type : null, 'rarity' => $item ? $item->rarity : null,
            'classification' => $classification, 'icon_url' => $asset, 'is_stackable' => (bool) $row,
            'quantity' => $total, 'locked_quantity' => $locked, 'available_quantity' => $available,
            'refinement_level' => $instance ? (int) $instance->refinement_level : null,
            'status' => $instance ? $instance->status : 'available',
            'equipped' => (bool) ($instance && ($instance->status === 'equipped' || $equipment->has($instance->id))),
            'base_sell_price' => $item ? (int) $item->sell_price : 0,
            'purchase_rate_basis_points' => (int) $shop->purchase_rate_basis_points,
            'unit_gold' => $unit, 'max_total_gold' => $maximum, 'can_sell' => $reason === null,
            'blocked_reason_code' => $reason, 'blocked_reason' => $reason, 'warning_code' => $warning,
            'stable_id' => $row ? (int) $row->id : (int) $instance->id,
        ]);
    }
}
