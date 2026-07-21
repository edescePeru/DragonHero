<?php

namespace Tests\Feature;

use App\Domain\Combat\Manual\CombatParticipantStatus;
use App\Domain\Combat\Manual\CombatParticipantType;
use App\Domain\Combat\Manual\ManualCombatActionRequestStatus;
use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Combat\Manual\ManualCombatTurnService;
use App\Domain\Combat\CombatActionResolver;
use App\Domain\Combat\CombatTurnOrder;
use App\Domain\Combat\Manual\AutomaticCombatActionSelector;
use App\Domain\Combat\Manual\CombatStateRebuilder;
use App\Domain\Combat\Manual\ManualCombatStatePersistenceService;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterWallet;
use App\Models\CombatActionRequest;
use App\Models\CombatEvent;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\GoldTransaction;
use App\Models\HuntReward;
use App\Models\HuntingSession;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use Database\Seeders\WorldCatalogSeeder;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ManualCombatActionSequenceRandom implements RandomNumberGenerator
{
    public $calls = 0; private $values;
    public function __construct(array $values) { $this->values = $values; }
    public function randomInt(int $minimum, int $maximum): int { $this->calls++; $value = array_shift($this->values); return $value === null ? $maximum : max($minimum, min($maximum, $value)); }
}

class ManualCombatActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(WorldCatalogSeeder::class); $this->seed(CharacterLevelRequirementSeeder::class); }
    private function player(array $attributes = []) { return Character::factory()->selected()->for(User::factory())->create($attributes); }
    private function combat(Character $character)
    {
        $zone=Zone::where('code','grey_oak_forest')->firstOrFail();
        $session=HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character,$zone)->id());
        $state=app(ManualCombatCreationService::class)->create($character->user,$character,$session);
        return CombatSession::findOrFail($state->id());
    }
    private function target(CombatSession $combat) { return $combat->participants()->where('participant_type',CombatParticipantType::MONSTER)->firstOrFail(); }
    private function payload(CombatSession $combat, CombatParticipant $target, $uuid = null)
    { return ['action_type'=>'basic_attack','target_participant_id'=>$target->id,'client_action_id'=>$uuid ?: (string)Str::uuid(),'expected_lock_version'=>$combat->fresh()->lock_version]; }
    private function rng(array $values) { $rng=new ManualCombatActionSequenceRandom($values); $this->app->instance(RandomNumberGenerator::class,$rng); return$rng; }

    public function test_schema_indexes_relations_and_events_are_immutable()
    {
        foreach(['combat_action_requests','combat_events']as$name){$table=DB::selectOne('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$name]);$this->assertSame('InnoDB',$table->ENGINE);$this->assertSame('utf8mb4_unicode_ci',$table->TABLE_COLLATION);}
        $actionIndexes=collect(DB::select("SHOW INDEX FROM combat_action_requests WHERE Key_name='combat_action_requests_idempotency_unique'"));
        $eventIndexes=collect(DB::select("SHOW INDEX FROM combat_events WHERE Key_name='combat_events_sequence_unique'"));
        $this->assertCount(2,$actionIndexes);$this->assertCount(2,$eventIndexes);
        $combat=$this->combat($this->player());$event=$combat->events()->firstOrFail();
        $this->assertSame($combat->id,$event->combatSession->id);
        $this->expectException(LogicException::class);$event->update(['event_type'=>'changed']);
    }

    public function test_basic_attack_victory_events_idempotency_and_scoped_rewards()
    {
        $character=$this->player(['base_attack'=>500]);$experience=$character->experience;$combat=$this->combat($character);$target=$this->target($combat);$uuid=(string)Str::uuid();$rng=$this->rng([1,10000]);
        $response=$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$target,$uuid));
        $response->assertOk()->assertJsonPath('idempotent_replay',false)->assertJsonPath('combat.status',ManualCombatStatus::WON)->assertJsonPath('combat.actions_available',[]);
        $this->assertSame(0,$target->fresh()->current_hp);$this->assertSame(CombatParticipantStatus::DEFEATED,$target->fresh()->status);
        $this->assertNull($combat->fresh()->active_slot);$this->assertNull($combat->fresh()->current_participant_id);$this->assertNotNull($combat->fresh()->completed_at);
        $types=collect($response->json('events'))->pluck('type');$this->assertContains(ManualCombatEventType::BASIC_ATTACK,$types);$this->assertContains(ManualCombatEventType::PARTICIPANT_DEFEATED,$types);$this->assertContains(ManualCombatEventType::COMBAT_WON,$types);
        $this->assertArrayNotHasKey('rolls',$response->json('events.0.payload'));$this->assertArrayHasKey('rolls',CombatEvent::where('event_type',ManualCombatEventType::BASIC_ATTACK)->latest('id')->first()->payload);
        $count=CombatEvent::count();$randomCalls=$rng->calls;$replay=$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$target,$uuid));
        $replay->assertOk()->assertJsonPath('idempotent_replay',true);$this->assertSame($count,CombatEvent::count());$this->assertSame($randomCalls,$rng->calls);
        $request=CombatActionRequest::firstOrFail();$this->assertSame(ManualCombatActionRequestStatus::PROCESSED,$request->status);$this->assertSame(1,$request->lock_version_before);$this->assertSame(2,$request->lock_version_after);$this->assertNotNull($request->first_event_sequence);$this->assertNotNull($request->last_event_sequence);
        $this->assertSame(0,HuntReward::count());$this->assertNotNull($combat->fresh()->rewards_granted_at);$this->assertGreaterThan($experience,$character->fresh()->experience);$this->assertSame(1,GoldTransaction::count());
    }

    public function test_miss_monster_turn_and_new_round_return_to_player()
    {
        $character=$this->player();$combat=$this->combat($character);$target=$this->target($combat);$player=$combat->participants()->where('participant_type',CombatParticipantType::CHARACTER)->firstOrFail();$this->rng([10000,10000]);
        $response=$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$target));
        $response->assertOk()->assertJsonPath('combat.status',ManualCombatStatus::WAITING_PLAYER)->assertJsonPath('combat.round',2)->assertJsonPath('combat.current_participant_id',$player->id);
        $this->assertSame($target->max_hp,$target->fresh()->current_hp);$this->assertSame($player->max_hp,$player->fresh()->current_hp);
        $attacks=collect($response->json('events'))->where('type',ManualCombatEventType::BASIC_ATTACK);$this->assertCount(2,$attacks);$this->assertSame([0,0],$attacks->pluck('payload.targets.0.damage')->all());
        $this->assertContains(ManualCombatEventType::ROUND_STARTED,collect($response->json('events'))->pluck('type'));
    }

    public function test_critical_uses_shared_resolver_and_persists_expected_damage()
    {
        $character=$this->player(['base_attack'=>10]);$combat=$this->combat($character);$target=$this->target($combat);$target->update(['current_hp'=>1000,'max_hp'=>1000]);$snapshot=$target->stats_snapshot;$snapshot['max_health']=1000;$target->update(['stats_snapshot'=>$snapshot]);$this->rng([1,1,10000]);
        $response=$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$target));
        $attack=collect($response->json('events'))->firstWhere('type',ManualCombatEventType::BASIC_ATTACK);$expected=max(1,(int)round(10*(1-$snapshot['damage_reduction_rate']/100)*1.5));
        $this->assertTrue($attack['payload']['targets'][0]['critical']);$this->assertSame($expected,$attack['payload']['targets'][0]['damage']);$this->assertSame(1000-$expected,$target->fresh()->current_hp);
    }

    public function test_validation_security_stale_version_and_terminal_actions_are_rejected()
    {
        $character=$this->player();$combat=$this->combat($character);$target=$this->target($combat);$base=$this->payload($combat,$target);
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),array_merge($base,['action_type'=>'skill']))->assertStatus(422);
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),array_merge($base,['actor_participant_id'=>$combat->current_participant_id]))->assertStatus(422);
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),array_merge($base,['expected_lock_version'=>0]))->assertStatus(409);
        $ally=$combat->participants()->where('participant_type',CombatParticipantType::CHARACTER)->firstOrFail();
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$ally))->assertStatus(422);
        $target->update(['status'=>CombatParticipantStatus::DEFEATED,'current_hp'=>0]);$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$target))->assertStatus(409);$target->update(['status'=>CombatParticipantStatus::ALIVE,'current_hp'=>$target->max_hp]);
        $other=$this->player();$otherCombat=$this->combat($other);$foreignTarget=$this->target($otherCombat);$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$this->payload($combat,$foreignTarget))->assertStatus(422);
        $this->actingAs($other->user)->postJson(route('characters.manual-combats.actions.store',[$other,$combat]),$base)->assertNotFound();
        $combat->update(['status'=>ManualCombatStatus::ACTIVE]);$this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$base)->assertStatus(409);
        $combat->update(['status'=>ManualCombatStatus::WON,'active_slot'=>null,'current_participant_id'=>null]);
        $this->actingAs($character->user)->postJson(route('characters.manual-combats.actions.store',[$character,$combat]),$base)->assertStatus(409);
    }

    public function test_multiple_fast_monsters_advance_automatically_and_character_death_stops_all()
    {
        $character=$this->player();$combat=$this->combat($character);
        // The current combat is expanded only for this orchestration test; catalog selection itself is covered separately.
        $template=$this->target($combat);$second=$template->replicate();$second->position=2;$second->source_identifier=$template->source_identifier.':second';$second->initiative_position=2;$second->save();
        $player=$combat->participants()->where('participant_type',CombatParticipantType::CHARACTER)->firstOrFail();$template->update(['initiative_position'=>1]);$player->update(['initiative_position'=>3]);$combat->update(['status'=>ManualCombatStatus::ACTIVE,'current_participant_id'=>$template->id]);
        $this->rng([10000,10000]);DB::transaction(function()use($combat){$locked=CombatSession::whereKey($combat->id)->lockForUpdate()->first();$participants=CombatParticipant::where('combat_session_id',$combat->id)->orderBy('id')->lockForUpdate()->get();app(ManualCombatTurnService::class)->advanceAutomaticLocked($locked,$participants);});
        $this->assertSame(ManualCombatStatus::WAITING_PLAYER,$combat->fresh()->status);$this->assertSame($player->id,$combat->fresh()->current_participant_id);

        $loser=$this->player();$lossCombat=$this->combat($loser);$lossPlayer=$lossCombat->participants()->where('participant_type',CombatParticipantType::CHARACTER)->firstOrFail();$lossMonster=$this->target($lossCombat);$lossPlayer->update(['current_hp'=>1,'initiative_position'=>2]);$monsterStats=$lossMonster->stats_snapshot;$monsterStats['attack']=500;$lossMonster->update(['initiative_position'=>1,'stats_snapshot'=>$monsterStats]);$lossCombat->update(['status'=>ManualCombatStatus::ACTIVE,'current_participant_id'=>$lossMonster->id]);$lossRng=new ManualCombatActionSequenceRandom([1,10000]);$order=new CombatTurnOrder();$lossTurns=new ManualCombatTurnService(new CombatStateRebuilder($order),new AutomaticCombatActionSelector(),new CombatActionResolver($lossRng,$order),app(ManualCombatStatePersistenceService::class));
        DB::transaction(function()use($lossCombat,$lossTurns){$locked=CombatSession::whereKey($lossCombat->id)->lockForUpdate()->first();$participants=CombatParticipant::where('combat_session_id',$lossCombat->id)->orderBy('id')->lockForUpdate()->get();$lossTurns->advanceAutomaticLocked($locked,$participants);});
        $this->assertSame(0,$lossPlayer->fresh()->current_hp);$this->assertSame(ManualCombatStatus::LOST,$lossCombat->fresh()->status);$this->assertNull($lossCombat->fresh()->active_slot);
        $this->assertSame(1,CombatEvent::where('combat_session_id',$lossCombat->id)->where('event_type',ManualCombatEventType::COMBAT_LOST)->count());
    }

    public function test_state_read_filters_sequences_and_exposes_only_public_fields()
    {
        $character=$this->player();$combat=$this->combat($character);$last=$combat->events()->max('sequence');
        $response=$this->actingAs($character->user)->getJson(route('characters.manual-combats.show',[$character,$combat]).'?after_sequence='.($last-1));
        $response->assertOk()->assertJsonCount(1,'events')->assertJsonPath('last_event_sequence',$last)->assertJsonMissing(['stats_snapshot'=>$combat->participants->first()->stats_snapshot]);
        $this->assertTrue($response->json('participants.0.is_current_turn'));$this->assertSame(['basic_attack'],$response->json('actions_available'));
    }
}
