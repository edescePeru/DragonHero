<?php
namespace Tests\Feature;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use App\Models\Zone;
use App\Models\ZoneConnection;
use App\Domain\WorldCatalog\ZoneCatalogService;
use Database\Seeders\WorldCatalogSeeder;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class WorldCatalogNavigationTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(WorldCatalogSeeder::class);$this->seed(CharacterLevelRequirementSeeder::class);}
 private function player(){ $user=User::factory()->create();Character::factory()->selectedFor($user)->create();return $user; }
 public function test_guest_cannot_browse_catalog(){ $this->get('/worlds')->assertRedirect('/login'); }
 public function test_user_without_character_keeps_creation_flow(){ $this->actingAs(User::factory()->create())->get('/worlds')->assertRedirect(route('characters.create')); }
 public function test_player_can_navigate_world_region_and_zone(){ $user=$this->player();$world=World::where('code','eldoria')->firstOrFail();$region=$world->regions()->firstOrFail();$zone=$region->zones()->where('code','grey_oak_forest')->firstOrFail();$this->actingAs($user)->get('/worlds')->assertOk()->assertSee('Eldoria');$this->actingAs($user)->get(route('worlds.show',$world))->assertOk()->assertSee('Reino de Valtheria');$this->actingAs($user)->get(route('regions.show',$region))->assertOk()->assertSee('Bosque de Roblegris');$this->actingAs($user)->get(route('zones.show',$zone))->assertOk()->assertSee('Lobo gris')->assertSee('Pesos de configuración')->assertSee('Aldea del Alba')->assertSee('Minas Abandonadas'); }
 public function test_inactive_and_hidden_catalogs_are_not_visible(){ $user=$this->player();World::create(['code'=>'inactive_world','name'=>'Inactive','status'=>CatalogStatus::INACTIVE]);$hidden=World::create(['code'=>'hidden_world','name'=>'Hidden','status'=>CatalogStatus::HIDDEN]);$this->actingAs($user)->get('/worlds')->assertDontSee('Inactive')->assertDontSee('Hidden');$this->actingAs($user)->get(route('worlds.show',$hidden))->assertNotFound(); }
 public function test_character_sheet_and_dashboard_still_work(){ $user=$this->player();$character=$user->characters()->firstOrFail();$this->actingAs($user)->get('/')->assertOk();$this->actingAs($user)->get(route('characters.show',$character))->assertOk(); }
 public function test_seeder_is_idempotent(){ $this->seed(WorldCatalogSeeder::class);$this->assertSame(1,World::where('code','eldoria')->count());$this->assertSame(3,Zone::count()); }
 public function test_incoming_route_requires_bidirectional_or_explicit_reverse_connection(){
  $village=Zone::where('code','dawn_village')->firstOrFail();$mines=Zone::where('code','abandoned_mines')->firstOrFail();
  ZoneConnection::create(['from_zone_id'=>$mines->id,'to_zone_id'=>$village->id,'travel_type'=>'path','is_bidirectional'=>false,'minimum_level'=>1,'status'=>'active']);
  $detail=app(ZoneCatalogService::class)->zoneDetail($village);$this->assertFalse($detail->incomingConnections->contains('from_zone_id',$mines->id));
  ZoneConnection::create(['from_zone_id'=>$village->id,'to_zone_id'=>$mines->id,'travel_type'=>'path','is_bidirectional'=>false,'minimum_level'=>1,'status'=>'active']);
  $detail=app(ZoneCatalogService::class)->zoneDetail($village);$this->assertTrue($detail->incomingConnections->contains('from_zone_id',$mines->id));
 }
}
