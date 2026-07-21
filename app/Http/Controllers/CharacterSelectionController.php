<?php
namespace App\Http\Controllers;
use App\Domain\Characters\Accounts\ActiveCharacterContext;use App\Domain\Characters\Selection\CharacterSelectionService;use App\Models\Character;use Illuminate\Http\Request;
final class CharacterSelectionController extends Controller{public function index(Request $request,CharacterSelectionService $service){if(!$request->user()->characters()->exists())return redirect()->route('characters.create');return view('characters.select',$service->forUser($request->user()));}public function store(Request $request,Character $character,ActiveCharacterContext $context){$this->authorize('view',$character);$context->select($request->user(),$character);return redirect()->route('dashboard')->with('status','Personaje seleccionado correctamente.');}}
