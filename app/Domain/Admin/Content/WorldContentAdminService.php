<?php

namespace App\Domain\Admin\Content;

use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Domain\WorldCatalog\WorldCatalogValidator;
use App\Models\Region;
use App\Models\World;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WorldContentAdminService
{
    private $catalog;
    private $images;

    public function __construct(WorldCatalogValidator $catalog, CatalogImageService $images)
    {
        $this->catalog = $catalog;
        $this->images = $images;
    }

    public function saveWorld(array $data, World $world = null)
    {
        $image = isset($data['image']) && $data['image'] instanceof UploadedFile ? $data['image'] : null;
        unset($data['image']);

        $model = DB::transaction(function () use ($data, $world) {
            $this->catalog->assertStatus($data['status']);
            $data['code'] = $this->catalog->normalizeCode($data['code']);
            $model = $world && $world->exists
                ? World::whereKey($world->id)->lockForUpdate()->firstOrFail()
                : new World();
            $model->fill($data);
            $model->save();

            return $model;
        }, 3);

        if ($image) {
            $this->images->replace($model, CatalogImageType::WORLD, $image);
        }

        return $model;
    }

    public function activateWorld(World $world)
    {
        $this->changeWorldStatus($world, CatalogStatus::ACTIVE);
    }

    public function deactivateWorld(World $world)
    {
        $this->changeWorldStatus($world, CatalogStatus::INACTIVE);
    }

    public function deleteWorldImage(World $world)
    {
        $this->images->delete($world, CatalogImageType::WORLD);
    }

    public function saveRegion(array $data, Region $region = null, World $context = null)
    {
        return DB::transaction(function () use ($data, $region, $context) {
            $model = $region && $region->exists
                ? Region::whereKey($region->id)->lockForUpdate()->firstOrFail()
                : new Region();

            if ($context) {
                if (isset($data['world_id']) && (int) $data['world_id'] !== (int) $context->id) {
                    throw new InvalidArgumentException('El mundo enviado no coincide con el contexto de la ruta.');
                }
                $data['world_id'] = $context->id;
            }

            if ($model->exists && (int) $data['world_id'] !== (int) $model->world_id) {
                throw new InvalidArgumentException('Una región existente no puede cambiar de mundo en esta fase.');
            }

            World::query()->whereKey($data['world_id'])->lockForUpdate()->firstOrFail();
            $this->catalog->assertStatus($data['status']);
            $this->catalog->assertLevelRange($data['recommended_level_min'], $data['recommended_level_max']);
            $data['code'] = $this->catalog->normalizeCode($data['code']);
            $model->fill($data);
            $model->save();

            return $model;
        }, 3);
    }

    public function activateRegion(Region $region)
    {
        $this->changeRegionStatus($region, CatalogStatus::ACTIVE);
    }

    public function deactivateRegion(Region $region)
    {
        $this->changeRegionStatus($region, CatalogStatus::INACTIVE);
    }

    private function changeWorldStatus(World $world, $status)
    {
        DB::transaction(function () use ($world, $status) {
            $model = World::whereKey($world->id)->lockForUpdate()->firstOrFail();
            $model->status = $status;
            $model->save();
        }, 3);
    }

    private function changeRegionStatus(Region $region, $status)
    {
        DB::transaction(function () use ($region, $status) {
            $model = Region::whereKey($region->id)->lockForUpdate()->firstOrFail();
            $model->status = $status;
            $model->save();
        }, 3);
    }
}
