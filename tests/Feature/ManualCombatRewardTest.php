<?php

namespace Tests\Feature;

use App\Domain\Combat\Manual\CombatParticipantType;
use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Combat\Manual\Rewards\CombatPendingRewardStatus;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CombatEvent;
use App\Models\CombatPendingReward;
use App\Models\CombatPendingRewardItem;
use App\Models\CombatSession;
use App\Models\GoldTransaction;
use App\Models\HuntReward;
use App\Models\HuntingSession;
use App\Models\Item;
use App\Models\MonsterLootEntry;
use App\Models\Monster;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualCombatRewardSequenceRandom implements RandomNumberGenerator
{
    public $calls = 0; private $values;
    public function __construct(array $values) { $this->values=$values; }
    public function randomInt(int $minimum,int $maximum):int { $this->calls++; $value=array_shift($this->values); return $value===null?$minimum:max($minimum,min($maximum,$value)); }
}

class ManualCombatRewardTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void { parent::setUp(); $this->seed(WorldCatalogSeeder::class); $this->seed(CharacterLevelRequirementSeeder::class); }
    private function character(array $attributes=[]){return Character::factory()->selected()->for(User::factory())->create(array_merge(['base_attack'=>500],$attributes));}
    private function combat(Character $character,$enemyCount=1)
    {
        $zone=Zone::where('code','grey_oak_forest')->firstOrFail();
        ZoneEncounterSize::where('zone_id',$zone->id)->delete(); ZoneEncounterSize::create(['zone_id'=>$zone->id,'enemy_count'=>$enemyCount,'weight'=>100,'is_active'=>true,'sort_order'=>1]);
        $wolf=Monster::where('code','grey_wolf')->firstOrFail();DB::table('zone_monsters')->where('zone_id',$zone->id)->where('monster_id','<>',$wolf->id)->update(['status'=>'inactive']);
        $hunting=HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character,$zone)->id());
        app(ManualCombatCreationService::class)->create($character->user,$character,$hunting);
        return CombatSession::where('character_id',$character->id)->latest('id')->firstOrFail();
    }
    private function target(CombatSession $combat){return $combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->where('status','alive')->firstOrFail();}
    private function guaranteedLoot()
    {
        $entry=MonsterLootEntry::whereHas('monster',function($q){$q->where('code','grey_wolf');})->with('item')->firstOrFail();
        $entry->update(['drop_probability_ppm'=>1000000,'minimum_quantity'=>1,'maximum_quantity'=>1,'status'=>'active']); return$entry->fresh('item');
    }
    private function attack(Character $character,CombatSession $combat,$target,$uuid=null)
    {return$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),['action_type'=>'basic_attack','target_participant_id'=>$target->id,'client_action_id'=>$uuid?:(string)Str::uuid(),'expected_lock_version'=>$combat->fresh()->lock_version]);}
    private function rng(array $values){$rng=new ManualCombatRewardSequenceRandom($values);$this->app->instance(RandomNumberGenerator::class,$rng);return$rng;}

    public function test_reward_schema_is_mysql_compatible_and_unique_per_participant()
    {
        foreach(['combat_pending_rewards','combat_pending_reward_items']as$table){$schema=DB::selectOne('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);$this->assertSame('InnoDB',$schema->ENGINE);$this->assertSame('utf8mb4_unicode_ci',$schema->TABLE_COLLATION);}
        $indexes=collect(DB::select("SHOW INDEX FROM combat_pending_rewards WHERE Key_name='combat_pending_rewards_source_unique'"));$this->assertCount(2,$indexes);
    }

    public function test_monster_death_generates_traced_reward_and_delivers_only_this_combat()
    {
        $entry=$this->guaranteedLoot();$character=$this->character();$combat=$this->combat($character);$target=$this->target($combat);$beforeExperience=(int)$character->experience;$rng=$this->rng([1,10000,4,1]);
        $response=$this->attack($character,$combat,$target)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::WON)->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::GRANTED);
        $reward=CombatPendingReward::firstOrFail();$this->assertSame($combat->id,(int)$reward->combat_session_id);$this->assertSame($target->id,(int)$reward->source_participant_id);$this->assertSame((int)$target->source_id,(int)$reward->source_monster_id);$this->assertSame(4,(int)$reward->gold_amount);$this->assertGreaterThan(0,(int)$reward->experience_amount);$this->assertSame(CombatPendingRewardStatus::GRANTED,$reward->status);
        $this->assertDatabaseHas('combat_pending_reward_items',['combat_pending_reward_id'=>$reward->id,'item_id'=>$entry->item_id,'quantity'=>1]);
        $metadata=$reward->items()->firstOrFail()->generation_metadata;$this->assertSame(2,$metadata['version']);$this->assertSame(1000000,$metadata['configured_probability_ppm']);$this->assertSame(1,$metadata['roll_ppm']);$this->assertArrayNotHasKey('configured_chance_basis_points',$metadata);
        $types=collect($response->json('events'))->pluck('type');$this->assertContains(ManualCombatEventType::REWARD_GENERATED,$types);$this->assertContains(ManualCombatEventType::REWARDS_GRANTED,$types);
        $this->assertNotNull($combat->fresh()->rewards_granted_at);$this->assertGreaterThan($beforeExperience,(int)$character->fresh()->experience);$this->assertSame(1,GoldTransaction::where('reference_type','combat_session')->where('reference_id',$combat->id)->count());
        $this->assertGreaterThanOrEqual(4,$rng->calls);$this->assertSame(0,HuntReward::count());
    }

    public function test_full_inventory_preserves_victory_and_all_rewards_pending_then_scoped_claim_is_idempotent()
    {
        $entry=$this->guaranteedLoot();$character=$this->character(['base_inventory_slots'=>6]);$filler=Item::create(['code'=>'combat_full','name'=>'Llenado','item_type'=>'material','equipment_type'=>null,'rarity'=>'common','is_stackable'=>true,'max_stack'=>2,'status'=>'active']);CharacterItem::create(['character_id'=>$character->id,'item_id'=>$filler->id,'quantity'=>2,'locked_quantity'=>0]);
        $combat=$this->combat($character);$character->base_inventory_slots=1;$character->save();$target=$this->target($combat);$beforeExperience=(int)$character->experience;$this->rng([1,10000,5,1]);
        $response=$this->attack($character,$combat,$target)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::WON);
        $this->assertSame(CombatPendingRewardStatus::PENDING_CLAIM,$response->json('combat.rewards.status'));
        $response->assertJsonPath('combat.rewards.claim_available',true);
        $this->assertNull($combat->fresh()->active_slot);$this->assertNull($combat->fresh()->rewards_granted_at);$this->assertSame(CombatPendingRewardStatus::PENDING_CLAIM,CombatPendingReward::first()->status);$this->assertSame($beforeExperience,(int)$character->fresh()->experience);$this->assertSame(0,GoldTransaction::count());$this->assertDatabaseMissing('character_items',['character_id'=>$character->id,'item_id'=>$entry->item_id]);$this->assertContains(ManualCombatEventType::REWARDS_PENDING_CLAIM,collect($response->json('events'))->pluck('type'));
        $character->base_inventory_slots=3;$character->save();$claim=$this->actingAs($character->user)->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertOk()->assertJsonPath('idempotent_replay',false)->assertJsonPath('rewards.status',CombatPendingRewardStatus::GRANTED)->assertJsonStructure(['inventory_html']);
        $this->assertStringContainsString('id="manual-combat-inventory-panel"',$claim->json('inventory_html'));
        $goldCount=GoldTransaction::count();$quantity=(int)CharacterItem::where('character_id',$character->id)->where('item_id',$entry->item_id)->value('quantity');
        $this->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertOk()->assertJsonPath('idempotent_replay',true);$this->assertSame($goldCount,GoldTransaction::count());$this->assertSame($quantity,(int)CharacterItem::where('character_id',$character->id)->where('item_id',$entry->item_id)->value('quantity'));
    }

    public function test_replayed_final_action_does_not_regenerate_or_redeliver()
    {
        $this->guaranteedLoot();$character=$this->character();$combat=$this->combat($character);$target=$this->target($combat);$uuid=(string)Str::uuid();$rng=$this->rng([1,10000,6,1]);
        $this->attack($character,$combat,$target,$uuid)->assertOk();$counts=[CombatPendingReward::count(),CombatPendingRewardItem::count(),CombatEvent::count(),GoldTransaction::count(),$rng->calls];
        $this->attack($character,$combat,$target,$uuid)->assertOk()->assertJsonPath('idempotent_replay',true);$this->assertSame($counts,[CombatPendingReward::count(),CombatPendingRewardItem::count(),CombatEvent::count(),GoldTransaction::count(),$rng->calls]);
    }

    public function test_zero_item_reward_is_valid_and_still_grants_gold_and_experience()
    {
        MonsterLootEntry::whereHas('monster',function($q){$q->where('code','grey_wolf');})->update(['status'=>'inactive']);
        $character=$this->character();$combat=$this->combat($character);$this->rng([1,10000,3]);
        $this->attack($character,$combat,$this->target($combat))->assertOk()->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::GRANTED)->assertJsonCount(0,'combat.rewards.items');
        $this->assertSame(1,CombatPendingReward::count());$this->assertSame(0,CombatPendingRewardItem::count());$this->assertSame(1,GoldTransaction::count());
    }

    public function test_unexpected_reward_generation_failure_rolls_back_lethal_action_events_and_reward()
    {
        $character=$this->character();$combat=$this->combat($character);$target=$this->target($combat);$eventsBefore=CombatEvent::count();
        $this->app->instance(RandomNumberGenerator::class,new class implements RandomNumberGenerator{private $calls=0;public function randomInt(int $minimum,int $maximum):int{$this->calls++;if($this->calls>=3)throw new \RuntimeException('Controlled reward generation failure.');return$this->calls===1?$minimum:$maximum;}});
        $this->attack($character,$combat,$target)->assertStatus(500);
        $this->assertSame($target->max_hp,$target->fresh()->current_hp);$this->assertSame('alive',$target->fresh()->status);$this->assertSame($eventsBefore,CombatEvent::count());$this->assertSame(0,CombatPendingReward::count());$this->assertSame(ManualCombatStatus::WAITING_PLAYER,$combat->fresh()->status);
    }

    public function test_repeated_monster_species_generate_independent_rewards_and_consolidate_on_last_death()
    {
        $this->guaranteedLoot();$character=$this->character(['base_max_health'=>500,'current_health'=>500]);$combat=$this->combat($character,2);$first=$this->target($combat);$this->rng([1,10000,3,1,10000]);
        $this->attack($character,$combat,$first)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::WAITING_PLAYER)->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::PENDING);
        $this->assertSame(1,CombatPendingReward::count());$this->assertNull($combat->fresh()->rewards_granted_at);
        $second=$this->target($combat->fresh());$this->rng([1,10000,4,1]);$this->attack($character,$combat->fresh(),$second)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::WON)->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::GRANTED);
        $rewards=CombatPendingReward::orderBy('source_participant_id')->get();$this->assertCount(2,$rewards);$this->assertCount(2,$rewards->pluck('source_participant_id')->unique());$this->assertCount(1,$rewards->pluck('source_monster_id')->unique());
    }

    public function test_character_defeat_forfeits_rewards_generated_earlier_in_same_combat()
    {
        $this->guaranteedLoot();$character=$this->character(['base_max_health'=>1,'current_health'=>1]);$combat=$this->combat($character,2);$first=$this->target($combat);$this->rng([1,10000,3,1]);
        $response=$this->attack($character,$combat,$first)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::LOST)->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::FORFEITED)->assertJsonPath('combat.rewards.claim_available',false);
        $reward=CombatPendingReward::firstOrFail();$this->assertSame(CombatPendingRewardStatus::FORFEITED,$reward->status);$this->assertNotNull($reward->forfeited_at);$this->assertNull($combat->fresh()->rewards_granted_at);$this->assertSame(0,GoldTransaction::count());$this->assertContains(ManualCombatEventType::REWARDS_FORFEITED,collect($response->json('events'))->pluck('type'));
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertStatus(409);
    }

    public function test_read_exposes_consolidated_public_reward_without_private_metadata()
    {
        $this->guaranteedLoot();$character=$this->character();$combat=$this->combat($character);$this->rng([1,10000,3,1]);$this->attack($character,$combat,$this->target($combat))->assertOk();
        $response=$this->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk()->assertJsonPath('rewards.status',CombatPendingRewardStatus::GRANTED)->assertJsonMissing(['generation_context'])->assertJsonMissing(['generation_metadata']);
        $this->assertGreaterThanOrEqual(3,$response->json('rewards.gold'));$this->assertLessThanOrEqual(8,$response->json('rewards.gold'));$this->assertNotEmpty($response->json('rewards.items'));
    }

    public function test_foreign_character_and_lost_combat_cannot_claim()
    {
        $character=$this->character();$combat=$this->combat($character);$combat->update(['status'=>ManualCombatStatus::LOST,'active_slot'=>null,'completed_at'=>now()]);$other=$this->character();
        $this->actingAs($other->user)->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertForbidden();
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertStatus(409);
    }
}
