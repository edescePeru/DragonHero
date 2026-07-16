<?php
namespace App\Http\Controllers;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Capacity\PendingRewardCapacityService;
use Carbon\CarbonImmutable;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\Item;
use App\Domain\Hunts\Rewards\HuntRewardService;
use App\Domain\Inventory\CharacterInventorySummaryService;
use App\Domain\Equipment\CharacterEquipmentSlot;
class CharacterInventoryController extends Controller {
 public function index(Character $character,CharacterInventorySummaryService $summaryService,HuntRewardService $rewards){
  $this->authorize('view',$character);
  $inventorySummary=$summaryService->snapshot($character);$itemIds=collect($inventorySummary['stackable_items'])->merge($inventorySummary['item_instances'])->pluck('item_id')->unique();
  $inventoryItems=$itemIds->isEmpty()?collect():Item::query()->whereKey($itemIds)->with(['mediaAssets'=>function($query){$query->where('asset_type',MediaAssetType::ICON);}])->get()->keyBy('id');
  $pendingRewardsSummary=$rewards->summaryPendingForCharacter($character);
  $equipmentSlotLabels=[];foreach(CharacterEquipmentSlot::all() as $slot)$equipmentSlotLabels[$slot]=CharacterEquipmentSlot::label($slot);
  return view('characters.inventory.index',compact('character','inventorySummary','inventoryItems','pendingRewardsSummary','equipmentSlotLabels'));
 }
}
