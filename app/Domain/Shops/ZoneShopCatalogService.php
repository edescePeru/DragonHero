<?php

namespace App\Domain\Shops;

use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Shop;
use App\Models\Character;
use App\Models\Zone;
use Carbon\CarbonImmutable;

final class ZoneShopCatalogService
{
    private $availability;

    public function __construct(ShopAvailabilityService $availability)
    {
        $this->availability = $availability;
    }

    public function forZone(Zone $zone, Character $character)
    {
        $shops = Shop::query()
            ->whereHas('locations', function ($query) use ($zone) {
                $query->where('locatable_type', ShopLocationType::ZONE)
                    ->where('locatable_id', $zone->id)
                    ->where('status', CatalogStatus::ACTIVE);
            })
            ->withCount(['offers as visible_offers_count' => function ($query) {
                $query->where('visibility', ShopOfferVisibility::VISIBLE);
            }])
            ->with(['npc.mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::PORTRAIT)->where('is_primary', true);
            }, 'mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::BANNER)->where('is_primary', true);
            }])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();

        $now = CarbonImmutable::now('UTC');

        return $shops->filter(function ($shop) use ($now) {
            return $this->availability->isShopVisible($shop, $now);
        })->map(function ($shop) use ($zone, $character) {
            $banner = $shop->mediaAssets->first();
            $portrait = $shop->npc->mediaAssets->first();

            return [
                'id' => (int) $shop->id,
                'name' => $shop->name,
                'description' => $shop->description,
                'npc_name' => $shop->npc->name,
                'banner_url' => $banner ? $banner->url() : null,
                'portrait_url' => $portrait ? $portrait->url() : null,
                'offers_count' => (int) $shop->visible_offers_count,
                'url' => route('characters.shops.show', [$character, $shop, 'zone' => $zone->id]),
            ];
        })->values()->all();
    }
}
