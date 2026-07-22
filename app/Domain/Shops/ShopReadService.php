<?php

namespace App\Domain\Shops;

use App\Domain\Inventory\Capacity\InventoryCapacityProjectionService;
use App\Domain\Media\MediaAssetType;
use App\Domain\Shops\Data\ShopViewData;
use App\Domain\Wallet\WalletService;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Shop;
use App\Models\ShopPurchase;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class ShopReadService
{
    private $availability;
    private $validator;
    private $wallet;
    private $projection;
    private $sales;

    public function __construct(ShopAvailabilityService $availability, ShopCatalogValidator $validator, WalletService $wallet, InventoryCapacityProjectionService $projection, ShopSaleReadService $sales)
    {
        $this->availability = $availability;
        $this->validator = $validator;
        $this->wallet = $wallet;
        $this->projection = $projection;
        $this->sales = $sales;
    }

    public function shop(User $user, Character $character, Shop $shop, $zoneId = null)
    {
        if ((int) $character->user_id !== (int) $user->id) {
            throw new AuthorizationException('Character does not belong to the authenticated user.');
        }

        $shop = Shop::query()->whereKey($shop->id)->with([
            'npc.mediaAssets' => $this->primaryTypes([MediaAssetType::PORTRAIT]),
            'mediaAssets' => $this->primaryTypes([MediaAssetType::BANNER, MediaAssetType::BACKGROUND]),
            'locations',
            'offers' => function ($query) {
                $query->where('visibility', ShopOfferVisibility::VISIBLE)
                    ->orderBy('category')->orderBy('sort_order')->orderBy('id');
            },
            'offers.item.mediaAssets' => $this->primaryTypes([MediaAssetType::ICON]),
        ])->firstOrFail();

        $now = CarbonImmutable::now('UTC');
        if (! $this->availability->isShopVisible($shop, $now)) {
            throw (new ModelNotFoundException())->setModel(Shop::class, [$shop->id]);
        }

        $returnUrl = $this->authorizeLocation($shop, $zoneId);
        $inventory = CharacterItem::where('character_id', $character->id)->get();
        $instances = ItemInstance::where('character_id', $character->id)->get();
        $itemIds = $inventory->pluck('item_id')->merge($instances->pluck('item_id'))->merge($shop->offers->pluck('item_id'))->unique();
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
        list($effective, $permanent, $temporary) = $this->projection->effectiveCapacity($character, $now);
        $baseCapacity = $this->projection->fromLoadedStateWithCapacity($character, $inventory, collect(), $items, $instances, $effective, $permanent, $temporary);
        $purchaseCounts = ShopPurchase::where('character_id', $character->id)
            ->whereIn('shop_offer_id', $shop->offers->pluck('id'))
            ->selectRaw('shop_offer_id, COUNT(*) as aggregate')->groupBy('shop_offer_id')->pluck('aggregate', 'shop_offer_id');
        $gold = $this->wallet->balance($character)->balance();

        $offers = $shop->offers->map(function ($offer) use ($shop, $character, $now, $inventory, $instances, $items, $effective, $permanent, $temporary, $purchaseCounts, $gold) {
            $offer->setRelation('shop', $shop);
            $count = (int) $purchaseCounts->get($offer->id, 0);
            $delivery = collect([(object) ['item_id' => $offer->item_id, 'quantity' => (int) $offer->quantity]]);
            try {
                $this->validator->validateSellableItem($offer->item, $offer->category);
                $capacity = $this->projection->fromLoadedStateWithCapacity($character, $inventory, $delivery, $items, $instances, $effective, $permanent, $temporary);
                $state = $this->offerState($offer, $character, $now, $count, $gold, $capacity->claimFits());
                $additionalSlots = max(0, $capacity->projectedUsedSlots() - $capacity->currentUsedSlots());
            } catch (InvalidArgumentException $exception) {
                $state = $this->state(false, 'invalid_item', 'No disponible', 'La configuración del objeto no es válida.');
                $additionalSlots = null;
            }
            $icon = $offer->item->mediaAssets->first();

            return array_merge([
                'id' => (int) $offer->id,
                'item_id' => (int) $offer->item_id,
                'item_name' => $offer->item->name,
                'item_code' => $offer->item->code,
                'search_text' => mb_strtolower($offer->item->name.' '.$offer->item->code, 'UTF-8'),
                'item_type' => $offer->item->item_type,
                'rarity' => $offer->item->rarity,
                'equipment_type' => $offer->item->equipment_type,
                'item_required_level' => (int) $offer->item->required_level,
                'category' => $offer->category,
                'category_label' => $this->categoryLabel($offer->category),
                'quantity' => (int) $offer->quantity,
                'gold_price' => (int) $offer->gold_price,
                'stock_remaining' => $offer->stock_remaining === null ? null : (int) $offer->stock_remaining,
                'purchase_count' => $count,
                'purchase_limit' => $offer->purchase_limit_per_character === null ? null : (int) $offer->purchase_limit_per_character,
                'required_level' => $offer->required_character_level === null ? null : (int) $offer->required_character_level,
                'icon_url' => $icon ? $icon->url() : null,
                'additional_slots' => $additionalSlots,
                'purchase_url' => route('characters.shops.offers.purchases.store', [$character, $shop, $offer]),
            ], $state);
        })->values()->all();

        $npcPortrait = $shop->npc->mediaAssets->first();
        $banner = $shop->mediaAssets->firstWhere('asset_type', MediaAssetType::BANNER);
        $background = $shop->mediaAssets->firstWhere('asset_type', MediaAssetType::BACKGROUND);
        $capacity = $baseCapacity->toArray();
        $saleCatalog = $this->sales->catalog($user, $character, $shop, $zoneId)->toArray();

        return new ShopViewData([
            'character' => $character,
            'shop' => ['id' => (int) $shop->id, 'name' => $shop->name, 'description' => $shop->description, 'banner_url' => $banner ? $banner->url() : null, 'background_url' => $background ? $background->url() : null],
            'npc' => ['name' => $shop->npc->name, 'greeting' => $shop->npc->greeting, 'portrait_url' => $npcPortrait ? $npcPortrait->url() : null],
            'offers' => $offers,
            'categories' => $this->categories($offers),
            'gold' => $gold,
            'inventory' => ['used' => $capacity['current_used_slots'], 'capacity' => $capacity['effective_capacity']],
            'return_url' => $returnUrl,
            'saleCatalog' => $saleCatalog,
            'shopCanBuy' => $saleCatalog['shop_can_buy'],
            'sellableCount' => $saleCatalog['sellable_count'],
        ]);
    }

    private function authorizeLocation(Shop $shop, $zoneId)
    {
        if ($shop->locations->isEmpty()) {
            return route('dashboard');
        }
        if (! is_string($zoneId) && ! is_int($zoneId) || ! preg_match('/^[1-9][0-9]*$/', (string) $zoneId)) {
            throw (new ModelNotFoundException())->setModel(Shop::class, [$shop->id]);
        }
        $allowed = $shop->locations->first(function ($location) use ($zoneId) {
            return $location->locatable_type === ShopLocationType::ZONE
                && (int) $location->locatable_id === (int) $zoneId
                && $location->status === CatalogStatus::ACTIVE;
        });
        if (! $allowed) {
            throw (new ModelNotFoundException())->setModel(Shop::class, [$shop->id]);
        }
        $zone = Zone::whereKey($zoneId)->where('status', CatalogStatus::ACTIVE)->firstOrFail();

        return route('zones.show', $zone);
    }

    private function offerState($offer, Character $character, CarbonImmutable $now, $count, $gold, $capacityFits)
    {
        if ($offer->status !== CatalogStatus::ACTIVE) return $this->state(false, 'inactive', 'Inactiva', 'La oferta no está activa.');
        if ($offer->starts_at && $now->lt($offer->starts_at)) return $this->state(false, 'future', 'Próximamente', 'La oferta todavía no ha comenzado.');
        if ($offer->ends_at && $now->gte($offer->ends_at)) return $this->state(false, 'ended', 'Finalizada', 'La oferta ha finalizado.');
        if ($offer->stock_remaining !== null && (int) $offer->stock_remaining === 0) return $this->state(false, 'out_of_stock', 'Agotada', 'No queda stock disponible.');
        if ($offer->purchase_limit_per_character !== null && $count >= (int) $offer->purchase_limit_per_character) return $this->state(false, 'purchase_limit', 'Límite alcanzado', 'Ya alcanzaste el límite de compras.');
        if ($offer->required_character_level !== null && (int) $character->level < (int) $offer->required_character_level) return $this->state(false, 'level_required', 'Nivel insuficiente', 'Requiere nivel '.$offer->required_character_level.'.');
        if ($gold < (int) $offer->gold_price) return $this->state(false, 'insufficient_gold', 'Oro insuficiente', 'No tienes oro suficiente.');
        if (! $capacityFits) return $this->state(false, 'inventory_full', 'Sin espacio', 'No hay capacidad para recibir la compra completa.');
        if (! $this->availability->isOfferPurchasable($offer, $now, $character)) return $this->state(false, 'unavailable', 'No disponible', 'La oferta no está disponible.');

        return $this->state(true, 'purchasable', 'Disponible', null);
    }

    private function state($purchasable, $code, $label, $reason)
    {
        return ['purchasable' => $purchasable, 'status_code' => $code, 'status_label' => $label, 'reason' => $reason];
    }

    private function categories(array $offers)
    {
        $present = [];
        foreach ($offers as $offer) $present[$offer['category']] = $offer['category_label'];
        return $present;
    }

    private function categoryLabel($category)
    {
        $labels = [ShopOfferCategory::WEAPONS => 'Armas', ShopOfferCategory::ARMOR => 'Armaduras', ShopOfferCategory::CONSUMABLES => 'Consumibles', ShopOfferCategory::MATERIALS => 'Materiales', ShopOfferCategory::RECIPES => 'Recetas', ShopOfferCategory::EVENT => 'Evento'];
        return isset($labels[$category]) ? $labels[$category] : $category;
    }

    private function primaryTypes(array $types)
    {
        return function ($query) use ($types) {
            $query->whereIn('asset_type', $types)->where('is_primary', true)->orderBy('sort_order')->orderBy('id');
        };
    }
}
