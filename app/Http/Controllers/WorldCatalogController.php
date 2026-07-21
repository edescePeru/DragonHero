<?php
namespace App\Http\Controllers;
use App\Domain\WorldMaps\Exceptions\WorldMapNotResolvableException;
use App\Domain\WorldMaps\WorldMapReadService;
use App\Domain\WorldMaps\WorldMapResolver;
use App\Domain\WorldCatalog\WorldCatalogService;
use App\Models\Region;
use App\Models\World;
class WorldCatalogController extends Controller {public function index(WorldCatalogService $service){return view('worlds.index',['worlds'=>$service->activeWorldCards()]);}public function show(World $world,WorldCatalogService $service,WorldMapResolver $resolver,WorldMapReadService $read){return$this->regionMap($world,null,$service,$resolver,$read);}public function region(World $world,Region $region,WorldCatalogService $service,WorldMapResolver $resolver,WorldMapReadService $read){return$this->regionMap($world,$region,$service,$resolver,$read);}private function regionMap(World $world,$region,WorldCatalogService $service,WorldMapResolver $resolver,WorldMapReadService $read){$navigation=$service->worldNavigation($world,$region);if(!$navigation['region'])return view('worlds.show',array_merge($navigation,['message'=>'Este mundo todavía no tiene regiones disponibles.']));try{$map=$resolver->forRegion($navigation['region']);}catch(WorldMapNotResolvableException $e){return view('worlds.show',array_merge($navigation,['message'=>'Mapa no disponible.']));}return view('world-maps.show',['worldMap'=>$read->showForWorldNavigation($map,view()->shared('navigationCharacter'),$navigation)]);}}
