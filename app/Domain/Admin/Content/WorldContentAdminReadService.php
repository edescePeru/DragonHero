<?php

namespace App\Domain\Admin\Content;

use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Region;
use App\Models\World;

final class WorldContentAdminReadService
{
    private $images;

    public function __construct(CatalogImageService $images)
    {
        $this->images = $images;
    }

    public function worlds()
    {
        $paginator = World::query()
            ->withCount('regions')
            ->with(['mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::IMAGE)
                    ->where('is_primary', true)
                    ->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25);

        $paginator->setCollection($paginator->getCollection()->map(function (World $world) {
            return $this->worldRow($world);
        }));

        return $paginator;
    }

    public function world(World $world)
    {
        $world->loadMissing([
            'mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::IMAGE)
                    ->where('is_primary', true)
                    ->orderBy('id');
            },
            'regions' => function ($query) {
                $query->with(['worldMaps' => function ($maps) {
                    $maps->where('is_default', true)->orderBy('sort_order')->orderBy('id');
                }])->orderBy('sort_order')->orderBy('name')->orderBy('id');
            },
        ]);

        return $this->worldRow($world);
    }

    public function regionOptions(Region $region = null, World $context = null)
    {
        $worlds = World::query()
            ->when(!$context, function ($query) use ($region) {
                $query->where(function ($statuses) use ($region) {
                    $statuses->where('status', CatalogStatus::ACTIVE);
                    if ($region && $region->exists) {
                        $statuses->orWhere('id', $region->world_id);
                    }
                });
            })
            ->when($context, function ($query) use ($context) {
                $query->whereKey($context->id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $selectedWorld = $context ?: (($region && $region->exists) ? $worlds->firstWhere('id', (int) $region->world_id) : null);

        return [
            'worlds' => $worlds,
            'statuses' => CatalogStatus::values(),
            'context_world' => $context,
            'world_locked' => (bool) $context || ($region && $region->exists),
            'selected_world_name' => $selectedWorld ? $selectedWorld->name : null,
        ];
    }

    public function regions(World $world = null)
    {
        return Region::query()
            ->with(['world', 'worldMaps' => function ($maps) {
                $maps->where('is_default', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->when($world, function ($query) use ($world) {
                $query->where('world_id', $world->id);
            })
            ->orderBy('world_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25);
    }

    private function worldRow(World $world)
    {
        $regionRows = $world->relationLoaded('regions') ? $world->regions->map(function (Region $region) {
            $defaultMap = $region->worldMaps->first();
            return [
                'model' => $region,
                'default_map' => $defaultMap,
                'has_default_map' => (bool) $defaultMap,
            ];
        })->all() : [];

        return [
            'model' => $world,
            'image' => $this->images->presentationFor($world, CatalogImageType::WORLD),
            'region_rows' => $regionRows,
            'statuses' => CatalogStatus::values(),
        ];
    }
}
