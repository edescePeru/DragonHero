<?php

namespace Tests\Feature;

use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Equipment\EquipmentEligibilityService;
use App\Domain\Inventory\Instances\ItemInstanceEventType;
use App\Domain\Inventory\Instances\Refinement\ItemRefinementService;
use App\Domain\Inventory\Instances\Refinement\RefinementRuleValidator;
use App\Domain\Inventory\Instances\Refinement\RefinementTokenService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Random\RandomNumberGenerator;
use App\Domain\Wallet\GoldReasonCode;
use App\Domain\Wallet\WalletService;
use App\Models\CharacterItem;
use App\Models\GoldTransaction;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\ItemInstanceEvent;
use App\Models\RefinementLevel;
use App\Models\RefinementLevelMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ControlledRefinementRandom implements RandomNumberGenerator
{
    public $calls = 0; private $rolls;
    public function __construct(array $rolls) { $this->rolls = $rolls; }
    public function randomInt(int $minimum, int $maximum): int { $this->calls++; if (!$this->rolls) throw new \RuntimeException('No controlled roll available.'); $roll=array_shift($this->rolls); if($roll<$minimum||$roll>$maximum)throw new \RuntimeException('Controlled roll outside range.'); return $roll; }
}

class ItemRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function fixture($chance = 10000, $level = 0)
    {
        $character=\App\Models\Character::factory()->create(['current_health'=>77]);
        $equipment=Item::create(['code'=>'test_refinement_sword_'.uniqid(),'name'=>'Testing Sword','item_type'=>'equipment','equipment_type'=>'weapon','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'attack_bonus'=>10,'status'=>'active']);
        $material=Item::create(['code'=>'test_refinement_ore_'.uniqid(),'name'=>'Testing Ore','item_type'=>'material','rarity'=>'common','is_stackable'=>true,'max_stack'=>99,'status'=>'active']);
        $instance=ItemInstance::factory()->create(['character_id'=>$character->id,'item_id'=>$equipment->id,'refinement_level'=>$level]);
        $rule=RefinementLevel::create(['from_level'=>$level,'to_level'=>$level+1,'success_chance_basis_points'=>$chance,'gold_cost'=>10,'failure_behavior'=>'keep_level','status'=>'active']);
        RefinementLevelMaterial::create(['refinement_level_id'=>$rule->id,'item_id'=>$material->id,'quantity'=>2]);
        app(InventoryService::class)->addItem($character,$material,10);
        app(WalletService::class)->credit($character,50,GoldReasonCode::ADMIN_GRANT);
        return compact('character','equipment','material','instance','rule');
    }

    private function serviceWithRolls(array $rolls, &$random)
    {
        $random=new ControlledRefinementRandom($rolls);
        $this->app->instance(RandomNumberGenerator::class,$random);
        return app(ItemRefinementService::class);
    }

    private function statsArray($stats)
    {
        return [$stats->maxHealth(),$stats->currentHealth(),$stats->attack(),$stats->defense(),$stats->accuracyRate(),$stats->evasionRate(),$stats->criticalChance(),$stats->criticalDamageMultiplier(),$stats->attackSpeed(),$stats->damageReductionRate(),$stats->lootBonus(),$stats->experienceBonus(),$stats->goldBonus(),$stats->power()];
    }

    public function test_success_boundaries_and_one_rng_call()
    {
        foreach([[10000,10000,0],[1,1,1],[8000,8000,2]] as $case){$data=$this->fixture($case[0],$case[2]);$service=$this->serviceWithRolls([$case[1]],$rng);$result=$service->refine($data['character'],$data['instance'],app(RefinementTokenService::class)->issue($data['character'],$data['instance']));$this->assertTrue($result->success());$this->assertSame(1,$rng->calls);}
    }

    public function test_failure_consumes_resources_preserves_instance_equipment_stats_and_health()
    {
        $data=$this->fixture(8000);
        app(CharacterEquipmentService::class)->equip($data['character'],$data['instance']->uuid,'weapon_main');
        $beforeStats=$this->statsArray(app(\App\Domain\Characters\CharacterStatsCalculator::class)->calculate($data['character']));
        $identity=$data['instance']->fresh()->only(['uuid','item_id','status']);
        $service=$this->serviceWithRolls([8001],$rng);
        $result=$service->refine($data['character'],$data['instance'],app(RefinementTokenService::class)->issue($data['character'],$data['instance']));
        $this->assertFalse($result->success());$this->assertSame('failed',$result->result());$this->assertSame(0,$result->currentLevel());$this->assertSame(1,$rng->calls);
        $this->assertSame(40,app(WalletService::class)->balance($data['character'])->balance());
        $this->assertSame(8,(int)CharacterItem::where('character_id',$data['character']->id)->where('item_id',$data['material']->id)->value('quantity'));
        $fresh=$data['instance']->fresh();$this->assertSame($identity,$fresh->only(['uuid','item_id','status']));$this->assertSame(0,$fresh->refinement_level);$this->assertSame(77,$data['character']->fresh()->current_health);
        $this->assertSame($beforeStats,$this->statsArray(app(\App\Domain\Characters\CharacterStatsCalculator::class)->calculate($data['character']->fresh())));
        $this->assertDatabaseHas('character_equipment',['character_id'=>$data['character']->id,'item_instance_id'=>$fresh->id,'slot'=>'weapon_main']);
        $this->assertSame(1,ItemInstanceEvent::where('event_type',ItemInstanceEventType::REFINEMENT_FAILED)->count());$this->assertSame(0,ItemInstanceEvent::where('event_type',ItemInstanceEventType::REFINEMENT_SUCCEEDED)->count());$this->assertSame(1,GoldTransaction::where('reason_code',GoldReasonCode::ITEM_REFINEMENT)->count());
    }

    public function test_failed_replay_returns_same_persisted_result_without_rng_or_double_consumption()
    {
        $data=$this->fixture(5000);$token=app(RefinementTokenService::class)->issue($data['character'],$data['instance']);$service=$this->serviceWithRolls([5001],$rng);$first=$service->refine($data['character'],$data['instance'],$token);$second=$service->refine($data['character'],$data['instance']->fresh(),$token);
        $this->assertFalse($first->replayed());$this->assertTrue($second->replayed());$this->assertSame($first->toArray()['roll'],$second->toArray()['roll']);$this->assertSame(1,$rng->calls);$this->assertSame(40,app(WalletService::class)->balance($data['character'])->balance());$this->assertSame(8,(int)CharacterItem::where('item_id',$data['material']->id)->value('quantity'));$this->assertSame(1,GoldTransaction::where('reason_code',GoldReasonCode::ITEM_REFINEMENT)->count());$this->assertSame(1,ItemInstanceEvent::where('event_type',ItemInstanceEventType::REFINEMENT_FAILED)->count());
    }

    public function test_distinct_token_after_failure_is_new_attempt_but_after_success_is_stale()
    {
        $data=$this->fixture(5000);$one=app(RefinementTokenService::class)->issue($data['character'],$data['instance']);$two=app(RefinementTokenService::class)->issue($data['character'],$data['instance']);$three=app(RefinementTokenService::class)->issue($data['character'],$data['instance']);$service=$this->serviceWithRolls([5001,1],$rng);$this->assertFalse($service->refine($data['character'],$data['instance'],$one)->success());$this->assertTrue($service->refine($data['character'],$data['instance']->fresh(),$two)->success());$this->assertSame(2,$rng->calls);$this->assertSame(1,$data['instance']->fresh()->refinement_level);
        try{$service->refine($data['character'],$data['instance']->fresh(),$three);$this->fail('Expected stale operation.');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('stale',$e->getMessage());}
    }

    public function test_operation_has_exactly_one_result_type_and_payload_is_complete()
    {
        $data=$this->fixture(1);$service=$this->serviceWithRolls([2],$rng);$result=$service->refine($data['character'],$data['instance'],app(RefinementTokenService::class)->issue($data['character'],$data['instance']));$payload=$result->toArray();foreach(['success','result','from_level','attempted_to_level','current_level','success_chance_basis_points','roll','failure_behavior','gold_consumed','materials_consumed','message','replayed'] as $key)$this->assertArrayHasKey($key,$payload);$event=ItemInstanceEvent::firstOrFail();$this->assertSame('failed',$event->metadata['result']);$this->assertSame($event->operation_uuid,GoldTransaction::where('reason_code',GoldReasonCode::ITEM_REFINEMENT)->value('idempotency_key'));
    }

    public function test_real_errors_rollback_without_rng_or_consumption()
    {
        $data=$this->fixture(5000);CharacterItem::where('character_id',$data['character']->id)->delete();$service=$this->serviceWithRolls([1],$rng);try{$service->refine($data['character'],$data['instance'],app(RefinementTokenService::class)->issue($data['character'],$data['instance']));$this->fail('Expected resource error.');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('materials',$e->getMessage());}$this->assertSame(0,$rng->calls);$this->assertSame(50,app(WalletService::class)->balance($data['character'])->balance());$this->assertSame(0,$data['instance']->fresh()->refinement_level);$this->assertSame(0,ItemInstanceEvent::whereIn('event_type',ItemInstanceEventType::refinementResults())->count());
    }

    public function test_probability_validator_accepts_range_and_rejects_outside_it()
    {
        foreach([1,8000,10000] as $chance)app(RefinementRuleValidator::class)->validate(new RefinementLevel(['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>$chance,'gold_cost'=>0,'failure_behavior'=>'keep_level','status'=>'active']));
        foreach([0,10001] as $chance){try{app(RefinementRuleValidator::class)->validate(new RefinementLevel(['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>$chance,'gold_cost'=>0,'failure_behavior'=>'keep_level','status'=>'active']));$this->fail('Expected invalid chance.');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('chance',$e->getMessage());}}
    }

    public function test_player_preview_and_exact_success_and_failure_messages()
    {
        $failed=$this->fixture(6000,0);$this->actingAs($failed['character']->user)->get(route('characters.inventory.index',$failed['character']))->assertOk()->assertSee('Probabilidad:')->assertSee('60 %')->assertSee('Se consumen el oro y los materiales; el objeto conserva su nivel y no se destruye.');
        $this->serviceWithRolls([6001],$failureRng);$failureToken=app(RefinementTokenService::class)->issue($failed['character'],$failed['instance']);$this->post(route('characters.item-instances.refine',[$failed['character'],$failed['instance']]),['refinement_token'=>$failureToken])->assertSessionHas('status','El refinamiento falló. El objeto permanece en +0.');

        $success=$this->fixture(6000,1);$this->actingAs($success['character']->user);$this->serviceWithRolls([1],$successRng);$successToken=app(RefinementTokenService::class)->issue($success['character'],$success['instance']);$this->post(route('characters.item-instances.refine',[$success['character'],$success['instance']]),['refinement_token'=>$successToken])->assertSessionHas('status','Refinamiento exitoso. El objeto subió a +2.');
    }

    public function test_item_can_be_refined_while_level_requirement_still_prevents_equipping()
    {
        $data=$this->fixture(10000);
        $data['equipment']->update(['required_level'=>10]);
        $service=$this->serviceWithRolls([1],$rng);
        $result=$service->refine($data['character'],$data['instance'],app(RefinementTokenService::class)->issue($data['character'],$data['instance']));

        $this->assertTrue($result->success());
        $this->assertSame(1,$data['instance']->fresh()->refinement_level);
        $item=$data['equipment']->fresh();
        $eligibility=app(EquipmentEligibilityService::class)->evaluate($data['character']->fresh(),$data['instance']->fresh(),$item,'weapon_main',$item->allowedCharacterClasses()->get(),$data['character']->characterClass);
        $this->assertFalse($eligibility->eligible());
        $this->assertContains('level_requirement_not_met',$eligibility->reasonCodes());
    }
}
