<?php
namespace Tests\Feature;
use App\Domain\Combat\Manual\CombatParticipantType;
use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Combat\Manual\Rewards\CombatPendingRewardStatus;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Hunts\Sessions\HuntingSessionStatus;
use App\Domain\Hunts\Sessions\HuntingSessionStopReason;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Character;
use App\Models\CombatEvent;
use App\Models\CombatLifecycleRequest;
use App\Models\CombatPendingReward;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use App\Models\Hunt;
use App\Models\Monster;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use Carbon\CarbonImmutable;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualCombatLifecycleRandom implements RandomNumberGenerator{private $values;public function __construct(array $values){$this->values=$values;}public function randomInt(int $minimum,int $maximum):int{$value=array_shift($this->values);return$value===null?$minimum:max($minimum,min($maximum,$value));}}

class ManualCombatLifecycleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void{parent::setUp();$this->seed(WorldCatalogSeeder::class);$this->seed(CharacterLevelRequirementSeeder::class);config(['game.manual_combat.expiration_minutes'=>30]);}
    protected function tearDown():void{CarbonImmutable::setTestNow();parent::tearDown();}
    private function character(array $attributes=[]){return Character::factory()->selected()->for(User::factory())->create(array_merge(['base_attack'=>500,'base_max_health'=>500,'current_health'=>500],$attributes));}
    private function context(Character $character,$count=1)
    {
        $zone=Zone::where('code','grey_oak_forest')->firstOrFail();ZoneEncounterSize::where('zone_id',$zone->id)->delete();ZoneEncounterSize::create(['zone_id'=>$zone->id,'enemy_count'=>$count,'weight'=>100,'is_active'=>true,'sort_order'=>1]);$wolf=Monster::where('code','grey_wolf')->firstOrFail();DB::table('zone_monsters')->where('zone_id',$zone->id)->where('monster_id','<>',$wolf->id)->update(['status'=>'inactive']);
        $hunting=HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character,$zone)->id());$state=app(ManualCombatCreationService::class)->create($character->user,$character,$hunting);return[$hunting,CombatSession::findOrFail($state->id())];
    }
    private function abandon(Character $character,CombatSession $combat,$uuid=null,$version=null){return$this->actingAs($character->user)->postJson(route('characters.manual-combats.abandon',[$character,$combat]),['client_request_id'=>$uuid?:(string)Str::uuid(),'expected_lock_version'=>$version===null?$combat->fresh()->lock_version:$version]);}
    private function attack(Character $character,CombatSession $combat,$target){return$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),['action_type'=>'basic_attack','target_participant_id'=>$target->id,'client_action_id'=>(string)Str::uuid(),'expected_lock_version'=>$combat->fresh()->lock_version]);}
    private function stale(CombatSession $combat,$minutes=31){DB::table('combat_sessions')->where('id',$combat->id)->update(['last_action_at'=>CarbonImmutable::now()->subMinutes($minutes)]);return$combat->fresh();}

    public function test_abandon_waiting_player_is_atomic_versioned_and_idempotent()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character);DB::table('combat_sessions')->where('id',$combat->id)->update(['last_action_at'=>CarbonImmutable::now()->subMinute()]);$combat=$combat->fresh();$before=(int)$combat->lock_version;$uuid=(string)Str::uuid();$last=$combat->last_action_at;
        $response=$this->abandon($character,$combat,$uuid,$before)->assertOk()->assertJsonPath('idempotent_replay',false)->assertJsonPath('combat.status',ManualCombatStatus::ABANDONED)->assertJsonPath('combat.can_abandon',false)->assertJsonPath('combat.actions_available',[])->assertJsonPath('combat.terminal_summary.result',ManualCombatStatus::ABANDONED);
        $fresh=$combat->fresh();$this->assertNull($fresh->active_slot);$this->assertNull($fresh->current_participant_id);$this->assertNotNull($fresh->completed_at);$this->assertTrue($fresh->last_action_at->gt($last));$this->assertSame($before+1,(int)$fresh->lock_version);$this->assertSame(1,CombatEvent::where('combat_session_id',$combat->id)->where('event_type',ManualCombatEventType::COMBAT_ABANDONED)->count());
        $this->assertSame(HuntingSessionStatus::STOPPED,$hunting->fresh()->status);$this->assertSame(HuntingSessionStopReason::MANUAL_COMBAT_ABANDONED,$hunting->fresh()->stop_reason);$this->assertNotNull($hunting->fresh()->stopped_at);$this->assertNull($hunting->fresh()->next_encounter_at);
        $stoppedAt=$hunting->fresh()->stopped_at->format('Y-m-d H:i:s.u');$stopReason=$hunting->fresh()->stop_reason;
        $events=CombatEvent::count();$this->abandon($character,$combat,$uuid,$before)->assertOk()->assertJsonPath('idempotent_replay',true);$this->assertSame($events,CombatEvent::count());$this->assertSame($before+1,(int)$combat->fresh()->lock_version);$this->assertSame(1,CombatLifecycleRequest::count());
        $this->assertSame($stoppedAt,$hunting->fresh()->stopped_at->format('Y-m-d H:i:s.u'));$this->assertSame($stopReason,$hunting->fresh()->stop_reason);
    }

    public function test_abandon_active_forfeits_generated_reward_without_resources_or_losing_lines()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character,2);$target=$combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->firstOrFail();$this->app->instance(RandomNumberGenerator::class,new ManualCombatLifecycleRandom([1,10000,3,10000,10000]));$this->attack($character,$combat,$target)->assertOk();
        $reward=CombatPendingReward::firstOrFail();$lineCount=$reward->items()->count();$goldBefore=$character->wallet?$character->wallet->gold_balance:0;$experienceBefore=$character->fresh()->experience;
        $combat->status=ManualCombatStatus::ACTIVE;$combat->current_participant_id=$combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->where('status','alive')->value('id');$combat->save();
        $this->abandon($character,$combat)->assertOk()->assertJsonPath('combat.rewards.status',CombatPendingRewardStatus::FORFEITED);
        $this->assertSame(CombatPendingRewardStatus::FORFEITED,$reward->fresh()->status);$this->assertNotNull($reward->fresh()->forfeited_at);$this->assertSame($lineCount,$reward->items()->count());$this->assertSame($experienceBefore,$character->fresh()->experience);$this->assertSame($goldBefore,$character->wallet?$character->wallet->fresh()->gold_balance:0);$event=CombatEvent::where('event_type',ManualCombatEventType::REWARDS_FORFEITED)->latest('id')->firstOrFail();$this->assertSame('abandoned',$event->payload['reason']);
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.rewards.claim',[$character,$combat]))->assertStatus(409);
    }

    public function test_lazy_expiration_respects_boundary_and_runs_only_once()
    {
        $now=CarbonImmutable::parse('2026-07-21 12:00:00');CarbonImmutable::setTestNow($now);$character=$this->character();list($hunting,$combat)=$this->context($character);DB::table('combat_sessions')->where('id',$combat->id)->update(['last_action_at'=>$now->subMinutes(29)]);
        $this->actingAs($character->user)->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk()->assertJsonPath('status',ManualCombatStatus::WAITING_PLAYER)->assertJsonPath('can_abandon',true);$this->assertSame($combat->lock_version,$combat->fresh()->lock_version);
        DB::table('combat_sessions')->where('id',$combat->id)->update(['last_action_at'=>$now->subMinutes(30)]);$before=(int)$combat->fresh()->lock_version;
        $response=$this->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk()->assertJsonPath('status',ManualCombatStatus::EXPIRED)->assertJsonPath('can_abandon',false)->assertJsonPath('seconds_until_expiration',null)->assertJsonPath('terminal_summary.result',ManualCombatStatus::EXPIRED);
        $this->assertNull($combat->fresh()->active_slot);$this->assertSame($before+1,(int)$combat->fresh()->lock_version);$this->assertSame(1,CombatEvent::where('event_type',ManualCombatEventType::COMBAT_EXPIRED)->count());$version=$combat->fresh()->lock_version;$events=CombatEvent::count();$this->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk();$this->assertSame($version,$combat->fresh()->lock_version);$this->assertSame($events,CombatEvent::count());
        $this->assertSame(HuntingSessionStatus::STOPPED,$hunting->fresh()->status);$this->assertSame(HuntingSessionStopReason::MANUAL_COMBAT_EXPIRED,$hunting->fresh()->stop_reason);$this->assertNull($hunting->fresh()->next_encounter_at);
        $activeCharacter=$this->character();list($activeHunting,$activeCombat)=$this->context($activeCharacter);$monster=$activeCombat->participants()->where('participant_type',CombatParticipantType::MONSTER)->firstOrFail();$activeCombat->status=ManualCombatStatus::ACTIVE;$activeCombat->current_participant_id=$monster->id;$activeCombat->save();$this->stale($activeCombat);$this->actingAs($activeCharacter->user)->getJson(route('characters.manual-combats.show',[$activeCharacter,$activeCombat]))->assertOk()->assertJsonPath('status',ManualCombatStatus::EXPIRED);
    }

    public function test_terminal_states_never_expire_and_active_read_recovers_automatic_turns()
    {
        foreach([ManualCombatStatus::WON,ManualCombatStatus::LOST,ManualCombatStatus::ABANDONED,ManualCombatStatus::EXPIRED]as$status){$character=$this->character();list($hunting,$combat)=$this->context($character);$combat->status=$status;$combat->active_slot=null;$combat->current_participant_id=null;$combat->completed_at=now();$combat->last_action_at=now()->subHours(2);$combat->save();$version=$combat->lock_version;$this->actingAs($character->user)->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk()->assertJsonPath('status',$status);$this->assertSame($version,$combat->fresh()->lock_version);}
        $character=$this->character();list($hunting,$combat)=$this->context($character);$monster=$combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->firstOrFail();$combat->status=ManualCombatStatus::ACTIVE;$combat->current_participant_id=$monster->id;$combat->save();$before=(int)$combat->lock_version;$this->app->instance(RandomNumberGenerator::class,new ManualCombatLifecycleRandom([10000]));$this->actingAs($character->user)->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk()->assertJsonPath('status',ManualCombatStatus::WAITING_PLAYER);$this->assertSame($before+1,(int)$combat->fresh()->lock_version);
    }

    public function test_new_combat_expires_old_slot_and_preserves_history()
    {
        $character=$this->character();list($hunting,$old)=$this->context($character);$old=$this->stale($old);$oldEvents=$old->events()->count();$state=app(ManualCombatCreationService::class)->create($character->user,$character,$hunting);$new=CombatSession::findOrFail($state->id());$this->assertNotSame($old->id,$new->id);$this->assertSame(ManualCombatStatus::EXPIRED,$old->fresh()->status);$this->assertNull($old->fresh()->active_slot);$this->assertSame($character->id,(int)$new->active_slot);$this->assertGreaterThan($oldEvents,$old->events()->count());$this->assertSame(1,CombatSession::whereNotNull('active_slot')->where('character_id',$character->id)->count());
    }

    public function test_expiration_forfeits_existing_rewards_and_new_action_cannot_continue()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character,2);$target=$combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->firstOrFail();$this->app->instance(RandomNumberGenerator::class,new ManualCombatLifecycleRandom([1,10000,3,10000,10000]));$this->attack($character,$combat,$target)->assertOk();$reward=CombatPendingReward::firstOrFail();$lineCount=$reward->items()->count();$combat=$this->stale($combat);
        $remaining=$combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->where('status','alive')->firstOrFail();$this->attack($character,$combat,$remaining)->assertStatus(409);$this->assertSame(ManualCombatStatus::EXPIRED,$combat->fresh()->status);$this->assertSame(CombatPendingRewardStatus::FORFEITED,$reward->fresh()->status);$this->assertSame($lineCount,$reward->items()->count());$this->assertSame(1,CombatEvent::where('event_type',ManualCombatEventType::COMBAT_EXPIRED)->count());$forfeit=CombatEvent::where('event_type',ManualCombatEventType::REWARDS_FORFEITED)->latest('id')->firstOrFail();$this->assertSame('expired',$forfeit->payload['reason']);
    }

    public function test_manual_hunting_session_stops_after_abandon_or_expiration_and_never_resumes_ticks()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character);$before=Hunt::count();app(HuntingSessionService::class)->tick($character,$hunting);$this->assertSame($before,Hunt::count());
        $this->abandon($character,$combat)->assertOk();app(HuntingSessionService::class)->tick($character,$hunting->fresh());$this->assertSame($before,Hunt::count());$this->assertSame(HuntingSessionStatus::STOPPED,$hunting->fresh()->status);app(HuntingSessionService::class)->start($character,$hunting->zone);
        $other=$this->character();list($otherHunting,$otherCombat)=$this->context($other);$otherBefore=Hunt::count();$this->stale($otherCombat);$this->actingAs($other->user)->getJson(route('characters.manual-combats.show',[$other,$otherCombat]))->assertOk()->assertJsonPath('status',ManualCombatStatus::EXPIRED);app(HuntingSessionService::class)->tick($other,$otherHunting->fresh());$this->assertSame($otherBefore,Hunt::count());$this->assertSame(HuntingSessionStatus::STOPPED,$otherHunting->fresh()->status);app(HuntingSessionService::class)->start($other,$otherHunting->zone);
    }

    public function test_start_recovers_only_exact_legacy_terminal_manual_session()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character);$combat->update(['status'=>ManualCombatStatus::WON,'active_slot'=>null,'current_participant_id'=>null,'completed_at'=>now()]);
        $new=app(HuntingSessionService::class)->start($character,$hunting->zone);$this->assertNotSame($hunting->id,$new->id());$this->assertSame(HuntingSessionStatus::STOPPED,$hunting->fresh()->status);$this->assertSame(HuntingSessionStopReason::MANUAL_COMBAT_WON,$hunting->fresh()->stop_reason);

        $activeCharacter=$this->character();list($activeHunting,$activeCombat)=$this->context($activeCharacter);$this->expectException(\App\Domain\Hunts\Sessions\Exceptions\ActiveHuntingSessionExistsException::class);app(HuntingSessionService::class)->start($activeCharacter,$activeHunting->zone);
    }

    public function test_abandon_terminal_rules_and_expiration_configuration_fallback()
    {
        config(['game.manual_combat.expiration_minutes'=>'invalid']);$this->assertSame(30,app(\App\Domain\Combat\Manual\ManualCombatExpirationPolicy::class)->minutes());config(['game.manual_combat.expiration_minutes'=>'7']);$this->assertSame(7,app(\App\Domain\Combat\Manual\ManualCombatExpirationPolicy::class)->minutes());
        $character=$this->character();list($hunting,$combat)=$this->context($character);$combat->status=ManualCombatStatus::WON;$combat->active_slot=null;$combat->current_participant_id=null;$combat->completed_at=now();$combat->save();$this->abandon($character,$combat)->assertStatus(409);
        $character2=$this->character();list($hunting2,$combat2)=$this->context($character2);$this->stale($combat2);$this->actingAs($character2->user)->getJson(route('characters.manual-combats.show',[$character2,$combat2]))->assertOk();$events=CombatEvent::count();$this->abandon($character2,$combat2)->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::EXPIRED);$this->assertSame($events,CombatEvent::count());
    }

    public function test_get_does_not_refresh_activity_and_stale_or_invalid_abandon_is_controlled()
    {
        $character=$this->character();list($hunting,$combat)=$this->context($character);$last=$combat->last_action_at->format('Y-m-d H:i:s.u');$response=$this->actingAs($character->user)->getJson(route('characters.manual-combats.show',[$character,$combat]))->assertOk();$this->assertIsString($response->json('expires_at'));$this->assertIsInt($response->json('seconds_until_expiration'));$this->assertGreaterThanOrEqual(0,$response->json('seconds_until_expiration'));$this->assertSame($last,$combat->fresh()->last_action_at->format('Y-m-d H:i:s.u'));
        $this->abandon($character,$combat,null,$combat->lock_version+1)->assertStatus(409);$this->assertSame(ManualCombatStatus::WAITING_PLAYER,$combat->fresh()->status);$this->actingAs($character->user)->postJson(route('characters.manual-combats.abandon',[$character,$combat]),['client_request_id'=>'invalid','expected_lock_version'=>$combat->lock_version])->assertStatus(422);
        $foreign=$this->character();$this->actingAs($foreign->user)->postJson(route('characters.manual-combats.abandon',[$character,$combat]),['client_request_id'=>(string)Str::uuid(),'expected_lock_version'=>$combat->lock_version])->assertForbidden();
    }
}
