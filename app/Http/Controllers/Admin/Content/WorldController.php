<?php

namespace App\Http\Controllers\Admin\Content;

use App\Domain\Admin\Content\WorldContentAdminReadService;
use App\Domain\Admin\Content\WorldContentAdminService;
use App\Domain\Media\CatalogImages\CatalogImageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\WorldRequest;
use App\Models\World;
use InvalidArgumentException;

final class WorldController extends Controller
{
    public function index(WorldContentAdminReadService $read)
    {
        return view('admin.content.worlds.index', ['worlds' => $read->worlds()]);
    }

    public function create(WorldContentAdminReadService $read)
    {
        $world = new World();
        $row = $read->world($world);

        return view('admin.content.worlds.form', ['world' => $world, 'worldRow' => $row]);
    }

    public function store(WorldRequest $request, WorldContentAdminService $service)
    {
        try {
            $world = $service->saveWorld($request->validated());
            return redirect()->route('admin.content.worlds.edit', $world)->with('status', 'Mundo creado correctamente.');
        } catch (CatalogImageException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }
    }

    public function edit(World $world, WorldContentAdminReadService $read)
    {
        return view('admin.content.worlds.form', ['world' => $world, 'worldRow' => $read->world($world)]);
    }

    public function update(WorldRequest $request, World $world, WorldContentAdminService $service)
    {
        try {
            $service->saveWorld($request->validated(), $world);
            return back()->with('status', 'Mundo actualizado correctamente.');
        } catch (CatalogImageException $e) {
            return back()->withInput()->withErrors(['image' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }
    }

    public function activate(World $world, WorldContentAdminService $service)
    {
        $service->activateWorld($world);
        return back()->with('status', 'Mundo activado correctamente.');
    }

    public function deactivate(World $world, WorldContentAdminService $service)
    {
        $service->deactivateWorld($world);
        return back()->with('status', 'Mundo desactivado correctamente.');
    }

    public function destroyImage(World $world, WorldContentAdminService $service)
    {
        $service->deleteWorldImage($world);
        return back()->with('status', 'Portada del mundo retirada correctamente.');
    }
}
