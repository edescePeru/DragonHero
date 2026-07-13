<?php
namespace Tests\Feature;
use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AddInventoryItemCommandTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(WorldCatalogSeeder::class);}
 private function character(){return Character::factory()->for(User::factory())->create();}
 public function test_command_adds_item(){ $character=$this->character();$this->artisan('game:inventory:add',['characterId'=>$character->id,'itemCode'=>'worn_leather','quantity'=>10])->expectsOutput('Total: 10')->expectsOutput('Locked: 0')->expectsOutput('Available: 10')->assertExitCode(0); }
 public function test_command_fails_in_production(){ $character=$this->character();app()->instance('env','production');$this->artisan('game:inventory:add',['characterId'=>$character->id,'itemCode'=>'worn_leather','quantity'=>1])->expectsOutput('This command is only available in local or testing.')->assertExitCode(1); }
 public function test_command_fails_for_missing_item(){ $this->artisan('game:inventory:add',['characterId'=>$this->character()->id,'itemCode'=>'missing','quantity'=>1])->expectsOutput('Item not found.')->assertExitCode(1); }
 public function test_command_fails_for_non_stackable_item(){ $character=$this->character();$item=Item::create(['code'=>'unique_item','name'=>'Unique','item_type'=>'equipment','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'status'=>'active']);$this->artisan('game:inventory:add',['characterId'=>$character->id,'itemCode'=>$item->code,'quantity'=>1])->assertExitCode(1); }
 public function test_command_fails_for_invalid_quantity(){ $this->artisan('game:inventory:add',['characterId'=>$this->character()->id,'itemCode'=>'worn_leather','quantity'=>0])->expectsOutput('Quantity must be a positive integer.')->assertExitCode(1); }
}
