<?php
namespace App\Http\Controllers;
use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\ZoneCatalogService;
use App\Models\Zone;
class ZoneCatalogController extends Controller { public function show(Zone $zone,ZoneCatalogService $service){$zone=$service->zoneDetail($zone);$zone->monsters->load(['mediaAssets'=>function($query){$query->where('asset_type',MediaAssetType::PORTRAIT);}]);return view('zones.show',['zone'=>$zone]);} }
