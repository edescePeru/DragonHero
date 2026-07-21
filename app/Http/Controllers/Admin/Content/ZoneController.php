<?php
namespace App\Http\Controllers\Admin\Content;use App\Domain\Admin\Content\ContentAdminReadService;use App\Domain\Admin\Content\ContentAdminService;use App\Http\Controllers\Controller;use App\Http\Requests\Admin\Content\ZoneRequest;use App\Models\Monster;use App\Models\Zone;use InvalidArgumentException;
final class ZoneController extends Controller{
public function index(ContentAdminReadService $read){return view('admin.content.zones.index',['zones'=>$read->zones()]);}
public function create(ContentAdminReadService $read){$zone=new Zone();return view('admin.content.zones.form',['zone'=>$zone,'options'=>$read->zoneOptions($zone)]);}
public function store(ZoneRequest $request,ContentAdminService $service){try{$zone=$service->saveZone($request->validated());return redirect()->route('admin.content.zones.show',$zone)->with('status','Zona creada.');}catch(InvalidArgumentException $e){return back()->withInput()->withErrors(['content'=>$e->getMessage()]);}}
public function show(Zone $zone,ContentAdminReadService $read){return view('admin.content.zones.show',['zone'=>$read->zone($zone),'monsters'=>Monster::orderBy('name')->get(),'statuses'=>$read->statuses()]);}
public function edit(Zone $zone,ContentAdminReadService $read){return view('admin.content.zones.form',['zone'=>$zone,'options'=>$read->zoneOptions($zone)]);}
public function update(ZoneRequest $request,Zone $zone,ContentAdminService $service){try{$service->saveZone($request->validated(),$zone);return redirect()->route('admin.content.zones.show',$zone)->with('status','Zona actualizada.');}catch(InvalidArgumentException $e){return back()->withInput()->withErrors(['content'=>$e->getMessage()]);}}
public function destroy(Zone $zone,ContentAdminService $service){$service->deactivateZone($zone);return back()->with('status','Zona desactivada; no se eliminó físicamente.');}
}
