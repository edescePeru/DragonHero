<?php

namespace Tests\Feature;

use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Hunts\HuntService;
use App\Domain\Inventory\Instances\Refinement\ItemRefinementService;
use App\Domain\Inventory\Instances\Refinement\RefinementTokenService;
use App\Domain\Random\NativeRandomNumberGenerator;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Character;
use App\Models\Hunt;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\RefinementLevel;
use App\Models\RefinementStatModifier;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HuntRefinementRandom implements RandomNumberGenerator
{
    private $roll;
    public function __construct($roll) { $this->roll = $roll; }
    public function randomInt(int $minimum, int $maximum): int { return $this->roll; }
}

class RefinementProbabilityHuntTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_preserves_stats_and_history_while_success_changes_only_future_hunts()
    {
        $this->seed(WorldCatalogSeeder::class);
        RefinementStatModifier::create(['refinement_level'=>1,'stat_increase_basis_points'=>10000,'status'=>'active']);
        $character=Character::factory()->for(User::factory())->create(['base_attack'=>10]);
        $item=Item::create(['code'=>'probability_hunt_weapon','name'=>'Probability Hunt Weapon','item_type'=>'equipment','equipment_type'=>'weapon','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'attack_bonus'=>10,'status'=>'active']);
        $instance=ItemInstance::factory()->create(['character_id'=>$character->id,'item_id'=>$item->id,'refinement_level'=>0]);
        app(CharacterEquipmentService::class)->equip($character,$instance->uuid,'main_hand');
        RefinementLevel::create(['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>5000,'gold_cost'=>0,'failure_behavior'=>'keep_level','status'=>'active']);
        $zone=Zone::where('code','grey_oak_forest')->firstOrFail();
        $historical=app(HuntService::class)->start($character,$zone);$historicalSnapshot=Hunt::findOrFail($historical->huntId())->character_stats_snapshot;

        $failedToken=app(RefinementTokenService::class)->issue($character,$instance);$this->app->instance(RandomNumberGenerator::class,new HuntRefinementRandom(5001));$failed=app(ItemRefinementService::class)->refine($character,$instance,$failedToken);$this->assertFalse($failed->success());
        $this->app->instance(RandomNumberGenerator::class,new NativeRandomNumberGenerator());$afterFailure=app(HuntService::class)->start($character,$zone);$this->assertSame($historicalSnapshot['effective'],Hunt::findOrFail($afterFailure->huntId())->character_stats_snapshot['effective']);

        $successToken=app(RefinementTokenService::class)->issue($character,$instance->fresh());$this->app->instance(RandomNumberGenerator::class,new HuntRefinementRandom(1));$success=app(ItemRefinementService::class)->refine($character,$instance->fresh(),$successToken);$this->assertTrue($success->success());
        $this->app->instance(RandomNumberGenerator::class,new NativeRandomNumberGenerator());$afterSuccess=app(HuntService::class)->start($character,$zone);$futureSnapshot=Hunt::findOrFail($afterSuccess->huntId())->character_stats_snapshot;
        $this->assertSame(30,$futureSnapshot['effective']['attack']);$this->assertSame(20,$historicalSnapshot['effective']['attack']);$this->assertSame($historicalSnapshot,Hunt::findOrFail($historical->huntId())->character_stats_snapshot);
    }
}
