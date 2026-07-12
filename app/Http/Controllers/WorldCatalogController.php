<?php
namespace App\Http\Controllers;
use App\Domain\WorldCatalog\WorldCatalogService;
use App\Models\World;
class WorldCatalogController extends Controller { public function index(WorldCatalogService $service){return view('worlds.index',['worlds'=>$service->activeWorlds()]);} public function show(World $world,WorldCatalogService $service){return view('worlds.show',['world'=>$service->worldDetail($world)]);} }
