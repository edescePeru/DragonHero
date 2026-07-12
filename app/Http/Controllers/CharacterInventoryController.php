<?php
namespace App\Http\Controllers;
use App\Domain\Inventory\InventoryService;
use App\Models\Character;
class CharacterInventoryController extends Controller {
 public function index(Character $character,InventoryService $inventory){$this->authorize('view',$character);$entries=$inventory->entries($character);return view('characters.inventory.index',compact('character','entries'));}
}
