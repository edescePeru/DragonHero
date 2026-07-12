<?php
namespace App\Http\Controllers;
use App\Domain\WorldCatalog\WorldCatalogService;
use App\Models\Region;
class RegionCatalogController extends Controller { public function show(Region $region,WorldCatalogService $service){return view('regions.show',['region'=>$service->regionDetail($region)]);} }
