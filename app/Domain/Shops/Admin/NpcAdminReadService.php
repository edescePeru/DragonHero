<?php

namespace App\Domain\Shops\Admin;

use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Npc;

final class NpcAdminReadService
{
    private $media;

    public function __construct(ShopMediaService $media)
    {
        $this->media = $media;
    }

    public function listing()
    {
        $page = Npc::withCount([
            'shops',
            'shops as active_shop_count' => function ($query) {
                $query->where('status', CatalogStatus::ACTIVE);
            },
        ])->with([
            'mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::PORTRAIT)
                    ->where('is_primary', true);
            },
        ])->orderBy('name')->paginate(25);

        $page->setCollection($page->getCollection()->map(function ($npc) {
            return [
                'model' => $npc,
                'portrait' => $this->media->presentation($npc, MediaAssetType::PORTRAIT),
                'active_shop_count' => (int) $npc->active_shop_count,
            ];
        }));

        return $page;
    }

    public function form(Npc $npc)
    {
        if ($npc->exists) {
            $npc->load([
                'mediaAssets' => function ($query) {
                    $query->where('asset_type', MediaAssetType::PORTRAIT)
                        ->where('is_primary', true);
                },
            ])->loadCount([
                'shops as active_shop_count' => function ($query) {
                    $query->where('status', CatalogStatus::ACTIVE);
                },
            ]);
        }

        return [
            'npc' => $npc,
            'portrait' => $this->media->presentation($npc, MediaAssetType::PORTRAIT),
            'statuses' => CatalogStatus::values(),
            'active_shop_count' => $npc->exists ? (int) $npc->active_shop_count : 0,
        ];
    }
}
