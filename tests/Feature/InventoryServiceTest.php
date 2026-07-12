<?php
namespace Tests\Feature;
use App\Domain\Inventory\Data\InventoryEntry;
use App\Domain\Inventory\Exceptions\InsufficientItemQuantityException;
use App\Domain\Inventory\InventoryService;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
class InventoryServiceTest extends TestCase {
 use RefreshDatabase;
 private $character; private $item; private $service;
 protected function setUp():void{parent::setUp();$this->character=Character::factory()->for(User::factory())->create();$this->item=Item::create(['code'=>'test_item','name'=>'Test Item','item_type'=>'material','rarity'=>'common','is_stackable'=>true,'max_stack'=>99,'status'=>'active']);$this->service=app(InventoryService::class);}
 public function test_adds_a_new_item(){ $entry=$this->service->addItem($this->character,$this->item,10);$this->assertInstanceOf(InventoryEntry::class,$entry);$this->assertSame(10,$entry->totalQuantity()); }
 public function test_adds_to_an_existing_item(){ $this->service->addItem($this->character,$this->item,10);$this->assertSame(17,$this->service->addItem($this->character,$this->item,7)->totalQuantity());$this->assertSame(1,CharacterItem::count()); }
 public function test_total_quantity_can_exceed_max_stack(){ $entry=$this->service->addItem($this->character,$this->item,437);$this->assertSame(437,$entry->totalQuantity()); }
 public function test_removes_partially(){ $this->service->addItem($this->character,$this->item,10);$this->assertSame(6,$this->service->removeItem($this->character,$this->item,4)->totalQuantity()); }
 public function test_deletes_row_at_zero(){ $this->service->addItem($this->character,$this->item,10);$this->assertNull($this->service->removeItem($this->character,$this->item,10));$this->assertSame(0,CharacterItem::count()); }
 public function test_cannot_remove_more_than_available(){ $this->service->addItem($this->character,$this->item,10);$this->service->lockItemQuantity($this->character,$this->item,8);try{$this->service->removeItem($this->character,$this->item,3);$this->fail();}catch(InsufficientItemQuantityException $e){$this->assertSame('insufficient_available_quantity',$e->reasonCode());} }
 public function test_locks_and_unlocks(){ $this->service->addItem($this->character,$this->item,10);$this->assertSame(7,$this->service->lockItemQuantity($this->character,$this->item,7)->lockedQuantity());$entry=$this->service->unlockItemQuantity($this->character,$this->item,2);$this->assertSame(5,$entry->lockedQuantity());$this->assertSame(5,$entry->availableQuantity()); }
 public function test_cannot_lock_more_than_available(){ $this->service->addItem($this->character,$this->item,2);$this->expectException(InsufficientItemQuantityException::class);$this->service->lockItemQuantity($this->character,$this->item,3); }
 public function test_unlock_error_has_locked_reason_code(){ $this->service->addItem($this->character,$this->item,2);try{$this->service->unlockItemQuantity($this->character,$this->item,1);$this->fail();}catch(InsufficientItemQuantityException $e){$this->assertSame('insufficient_locked_quantity',$e->reasonCode());} }
 public function test_has_item_uses_available_quantity(){ $this->service->addItem($this->character,$this->item,10);$this->service->lockItemQuantity($this->character,$this->item,8);$this->assertTrue($this->service->hasItem($this->character,$this->item,2));$this->assertFalse($this->service->hasItem($this->character,$this->item,5));$this->assertSame(2,$this->service->availableQuantity($this->character,$this->item)); }
 public function test_rejects_non_positive_quantities(){ foreach([0,-1] as $quantity){try{$this->service->addItem($this->character,$this->item,$quantity);$this->fail();}catch(InvalidArgumentException $e){$this->assertTrue(true);}} }
 public function test_rejects_non_stackable_inactive_and_hidden_items(){ foreach([['is_stackable'=>false,'status'=>'active'],['is_stackable'=>true,'status'=>'inactive'],['is_stackable'=>true,'status'=>'hidden']] as $i=>$state){$item=Item::create(['code'=>'bad_'.$i,'name'=>'Bad','item_type'=>'material','rarity'=>'common','max_stack'=>$state['is_stackable']?99:null]+$state);try{$this->service->addItem($this->character,$item,1);$this->fail();}catch(InvalidArgumentException $e){$this->assertTrue(true);}} }
 public function test_entries_returns_only_inventory_entries(){ $this->service->addItem($this->character,$this->item,3);$entries=$this->service->entries($this->character);$this->assertContainsOnlyInstancesOf(InventoryEntry::class,$entries); }
 public function test_item_referenced_by_inventory_cannot_be_deleted(){ $this->service->addItem($this->character,$this->item,1);$this->expectException(QueryException::class);$this->item->delete(); }
 public function test_failed_write_rolls_back_and_preserves_invariants(){ $this->service->addItem($this->character,$this->item,5);try{$this->service->removeItem($this->character,$this->item,6);}catch(InsufficientItemQuantityException $e){}$row=CharacterItem::firstOrFail();$this->assertSame(5,$row->quantity);$this->assertLessThanOrEqual($row->quantity,$row->locked_quantity); }
}
