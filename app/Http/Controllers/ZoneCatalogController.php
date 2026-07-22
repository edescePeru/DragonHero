<?php
namespace App\Http\Controllers;
use App\Domain\Media\MediaAssetType;
use App\Domain\Shops\ZoneShopCatalogService;
use App\Domain\WorldCatalog\ZoneCatalogService;
use App\Models\Zone;
class ZoneCatalogController extends Controller { public function show(Zone $zone,ZoneCatalogService $service,ZoneShopCatalogService $shops){$zone=$service->zoneDetail($zone);$zone->monsters->load(['mediaAssets'=>function($query){$query->where('asset_type',MediaAssetType::PORTRAIT);}]);$character=view()->shared('navigationCharacter');return view('zones.show',['zone'=>$zone,'shops'=>$character?$shops->forZone($zone,$character):[]]);} }
