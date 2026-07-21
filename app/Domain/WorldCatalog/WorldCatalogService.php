<?php
namespace App\Domain\WorldCatalog;
use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\Media\MediaAssetType;
use App\Models\World;
use App\Models\Region;
final class WorldCatalogService {
    private $images;
    public function __construct(CatalogImageService $images){$this->images=$images;}
    public function activeWorlds(){return World::query()->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name')->get();}
    public function activeWorldCards(){return World::query()->where('status',CatalogStatus::ACTIVE)->with(['mediaAssets'=>function($q){$q->where('asset_type',MediaAssetType::IMAGE)->where('is_primary',true)->orderBy('sort_order')->orderBy('id');}])->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()->map(function($world){$image=$this->images->presentationFor($world,CatalogImageType::WORLD);return['world'=>$world,'preview_url'=>$image->exists()?$image->url256():null];});}
    public function worldNavigation(World $world,Region $selected=null){$world=World::query()->whereKey($world->getKey())->where('status',CatalogStatus::ACTIVE)->with(['regions'=>function($q){$q->where('status',CatalogStatus::ACTIVE)->with(['worldMaps'=>function($maps){$maps->where('status','active')->where('is_default',true)->orderBy('sort_order')->orderBy('id');}])->orderBy('sort_order')->orderBy('name')->orderBy('id');}])->firstOrFail();$region=$selected?$world->regions->firstWhere('id',(int)$selected->id):$world->regions->first();if($selected&&!$region)abort(404);$options=$world->regions->filter(function($candidate){return$candidate->worldMaps->isNotEmpty();})->map(function($candidate)use($world){return['id'=>(int)$candidate->id,'name'=>$candidate->name,'url'=>route('worlds.regions.show',[$world,$candidate])];})->values()->all();return['world'=>$world,'region'=>$region,'regions'=>$options];}
    public function worldDetail(World $world){return World::query()->whereKey($world->getKey())->where('status',CatalogStatus::ACTIVE)->with(['regions'=>function($q){$q->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name');}])->firstOrFail();}
    public function regionDetail(Region $region){return Region::query()->whereKey($region->getKey())->where('status',CatalogStatus::ACTIVE)->whereHas('world',function($q){$q->where('status',CatalogStatus::ACTIVE);})->with(['world','zones'=>function($q){$q->where('status',CatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name');}])->firstOrFail();}
}
