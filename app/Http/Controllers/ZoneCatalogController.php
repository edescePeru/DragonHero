<?php
namespace App\Http\Controllers;
use App\Domain\WorldCatalog\ZoneCatalogService;
use App\Models\Zone;
class ZoneCatalogController extends Controller { public function show(Zone $zone,ZoneCatalogService $service){return view('zones.show',['zone'=>$service->zoneDetail($zone)]);} }
