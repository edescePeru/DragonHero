<?php
namespace Tests\Feature;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Region;
use App\Models\World;
use App\Models\Zone;
use App\Models\ZoneConnection;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;
class WorldCatalogRelationshipsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(WorldCatalogSeeder::class);}
 public function test_world_region_and_zone_relations(){ $world=World::where('code','eldoria')->firstOrFail();$region=Region::where('code','kingdom_of_valtheria')->firstOrFail();$zone=Zone::where('code','dawn_village')->firstOrFail();$this->assertTrue($world->regions->contains($region));$this->assertTrue($region->world->is($world));$this->assertTrue($region->zones->contains($zone));$this->assertTrue($zone->region->is($region)); }
 public function test_monsters_can_belong_to_multiple_zones(){ $monster=Monster::where('code','goblin_scout')->firstOrFail();$this->assertCount(2,$monster->zones);$this->assertGreaterThan(1,Zone::whereHas('monsters')->count()); }
 public function test_zone_has_incoming_and_outgoing_connections(){ $forest=Zone::where('code','grey_oak_forest')->firstOrFail();$this->assertCount(1,$forest->incomingConnections);$this->assertCount(1,$forest->outgoingConnections); }
 public function test_required_item_can_be_null(){ $this->assertNull(ZoneConnection::firstOrFail()->requiredItem); }
 public function test_unique_codes_use_their_documented_scopes(){ $world=World::firstOrFail();Region::create(['world_id'=>$world->id,'code'=>'shared','name'=>'A','recommended_level_min'=>1,'status'=>'active']);$other=World::create(['code'=>'other','name'=>'Other','status'=>'active']);Region::create(['world_id'=>$other->id,'code'=>'shared','name'=>'B','recommended_level_min'=>1,'status'=>'active']);$this->assertSame(2,Region::where('code','shared')->count()); }
 public function test_item_catalog_was_created(){ $this->assertSame(7,Item::count()); }
 public function test_region_code_cannot_repeat_inside_the_same_world(){ $world=World::firstOrFail();$this->expectException(QueryException::class);Region::create(['world_id'=>$world->id,'code'=>'kingdom_of_valtheria','name'=>'Duplicate','recommended_level_min'=>1,'status'=>'active']); }
 public function test_global_world_code_cannot_repeat(){ $this->expectException(QueryException::class);World::create(['code'=>'eldoria','name'=>'Duplicate','status'=>'active']); }
}
