<?php
namespace Tests\Feature;
use App\Domain\Inventory\InventoryService;
use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CharacterInventoryTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(WorldCatalogSeeder::class);}
 private function player(){ $user=User::factory()->create();$character=Character::factory()->for($user)->create();return [$user,$character]; }
 public function test_guest_cannot_view_inventory(){ list($user,$character)=$this->player();$this->get(route('characters.inventory.index',$character))->assertRedirect('/login'); }
 public function test_user_cannot_view_another_inventory(){ list($owner,$character)=$this->player();$other=User::factory()->create();Character::factory()->for($other)->create();$this->actingAs($other)->get(route('characters.inventory.index',$character))->assertForbidden(); }
 public function test_owner_sees_empty_inventory_message(){ list($user,$character)=$this->player();$this->actingAs($user)->get(route('characters.inventory.index',$character))->assertOk()->assertSee('El inventario está vacío'); }
 public function test_inventory_displays_correct_quantities(){ list($user,$character)=$this->player();$item=Item::where('code','worn_leather')->firstOrFail();$service=app(InventoryService::class);$service->addItem($character,$item,10);$service->lockItemQuantity($character,$item,8);$this->actingAs($user)->get(route('characters.inventory.index',$character))->assertSee('Cuero desgastado')->assertSeeInOrder(['10','8','2']); }
 public function test_character_sheet_links_to_inventory(){ list($user,$character)=$this->player();$this->actingAs($user)->get(route('characters.show',$character))->assertSee(route('characters.inventory.index',$character),false); }
 public function test_dashboard_and_catalog_still_work(){ list($user,$character)=$this->player();$this->actingAs($user)->get('/')->assertOk();$this->actingAs($user)->get('/worlds')->assertOk(); }
}
