<?php
namespace App\Http\Controllers;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Capacity\PendingRewardCapacityService;
use Carbon\CarbonImmutable;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\Item;
class CharacterInventoryController extends Controller {
 public function index(Character $character,InventoryService $inventory,PendingRewardCapacityService $capacityService){
  $this->authorize('view',$character);
  $entries=$inventory->entries($character);
  $itemIds=$entries->map(function($entry){return $entry->itemId();})->filter(function($id){return is_int($id)&&$id>0;})->unique()->values();
  $inventoryItems=$itemIds->isEmpty()?collect():Item::query()->whereKey($itemIds)->with(['mediaAssets'=>function($query){$query->where('asset_type',MediaAssetType::ICON);}])->get()->keyBy('id');
  $inventoryCapacity=$capacityService->snapshot($character,CarbonImmutable::now())->toArray();
  return view('characters.inventory.index',compact('character','entries','inventoryItems','inventoryCapacity'));
 }
}
