<?php
namespace App\Domain\WorldCatalog;
use App\Models\World;
use App\Models\Region;
final class WorldCatalogService {
    public function activeWorlds(){return World::query()->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name')->get();}
    public function worldDetail(World $world){return World::query()->whereKey($world->getKey())->where('status',CatalogStatus::ACTIVE)->with(['regions'=>function($q){$q->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name');}])->firstOrFail();}
    public function regionDetail(Region $region){return Region::query()->whereKey($region->getKey())->where('status',CatalogStatus::ACTIVE)->whereHas('world',function($q){$q->where('status',CatalogStatus::ACTIVE);})->with(['world','zones'=>function($q){$q->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name');}])->firstOrFail();}
}
