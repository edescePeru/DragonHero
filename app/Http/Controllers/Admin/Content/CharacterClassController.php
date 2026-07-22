<?php

namespace App\Http\Controllers\Admin\Content;

use App\Domain\Characters\Classes\CharacterClassAdminReadService;
use App\Domain\Characters\Classes\CharacterClassAdminService;
use App\Domain\Media\CatalogImages\CatalogImageException;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\CharacterClassRequest;
use App\Models\CharacterClass;
use InvalidArgumentException;

final class CharacterClassController extends Controller
{
    public function index(CharacterClassAdminReadService $read){return view('admin.content.character-classes.index', ['classes' => $read->listing()]);}
    public function create(CharacterClassAdminReadService $read){return view('admin.content.character-classes.form', ['row' => $read->emptyRow(), 'statuses' => $read->statuses()]);}
    public function edit(CharacterClass $character_class, CharacterClassAdminReadService $read){return view('admin.content.character-classes.form', ['row' => $read->detail($character_class), 'statuses' => $read->statuses()]);}
    public function store(CharacterClassRequest $request, CharacterClassAdminService $service){return $this->persist($request, $service, null);}
    public function update(CharacterClassRequest $request, CharacterClass $character_class, CharacterClassAdminService $service){return $this->persist($request, $service, $character_class);}
    public function activate(CharacterClass $character_class, CharacterClassAdminService $service){return $this->changeStatus($character_class, CatalogStatus::ACTIVE, $service, 'Clase activada correctamente.');}
    public function deactivate(CharacterClass $character_class, CharacterClassAdminService $service){return $this->changeStatus($character_class, CatalogStatus::INACTIVE, $service, 'Clase inactivada correctamente.');}
    public function hide(CharacterClass $character_class, CharacterClassAdminService $service){return $this->changeStatus($character_class, CatalogStatus::HIDDEN, $service, 'Clase ocultada correctamente.');}
    public function destroyIcon(CharacterClass $character_class, CharacterClassAdminService $service){$service->deleteIcon($character_class);return back()->with('status', 'Icono eliminado correctamente.');}

    private function persist($request, $service, $class)
    {
        try {
            $saved = $service->save($request->validated(), $class, $request->file('icon'));
            return redirect()->route('admin.content.character-classes.edit', $saved)->with('status', $class ? 'Clase actualizada correctamente.' : 'Clase creada correctamente.');
        } catch (CatalogImageException $e) {
            return back()->withInput()->withErrors(['icon' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['content' => $e->getMessage()]);
        }
    }

    private function changeStatus($class, $status, $service, $message)
    {
        try {$service->setStatus($class, $status);return back()->with('status', $message);}
        catch (InvalidArgumentException $e) {return back()->withErrors(['content' => $e->getMessage()]);}
    }
}
