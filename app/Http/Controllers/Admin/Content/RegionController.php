<?php

namespace App\Http\Controllers\Admin\Content;

use App\Domain\Admin\Content\WorldContentAdminReadService;
use App\Domain\Admin\Content\WorldContentAdminService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\RegionRequest;
use App\Models\Region;
use App\Models\World;
use InvalidArgumentException;

final class RegionController extends Controller
{
    public function index(WorldContentAdminReadService $read, World $world = null)
    {
        return view('admin.content.regions.index', ['regions' => $read->regions($world), 'contextWorld' => $world]);
    }

    public function create(WorldContentAdminReadService $read, World $world = null)
    {
        $region = new Region();
        if ($world) $region->world_id = $world->id;

        return view('admin.content.regions.form', [
            'region' => $region,
            'contextWorld' => $world,
            'options' => $read->regionOptions($region, $world),
        ]);
    }

    public function store(RegionRequest $request, WorldContentAdminService $service, World $world = null)
    {
        try {
            $region = $service->saveRegion($request->validated(), null, $world);
            return redirect()->route('admin.content.regions.edit', $region)->with('status', 'Región creada correctamente.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }
    }

    public function edit(Region $region, WorldContentAdminReadService $read)
    {
        $region->load('world');

        return view('admin.content.regions.form', [
            'region' => $region,
            'contextWorld' => null,
            'options' => $read->regionOptions($region),
        ]);
    }

    public function update(RegionRequest $request, Region $region, WorldContentAdminService $service)
    {
        try {
            $service->saveRegion($request->validated(), $region);
            return back()->with('status', 'Región actualizada correctamente.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }
    }

    public function activate(Region $region, WorldContentAdminService $service)
    {
        $service->activateRegion($region);
        return back()->with('status', 'Región activada correctamente.');
    }

    public function deactivate(Region $region, WorldContentAdminService $service)
    {
        $service->deactivateRegion($region);
        return back()->with('status', 'Región desactivada correctamente.');
    }
}
