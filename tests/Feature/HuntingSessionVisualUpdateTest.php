<?php
namespace Tests\Feature;

use App\Domain\Combat\CombatResultStatus;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Hunts\Sessions\HuntingSessionStopReason;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Hunt;
use App\Models\HuntReward;
use App\Models\HuntingSession;
use App\Models\Item;
use App\Models\Monster;
use App\Models\MonsterLootEntry;
use App\Models\User;
use App\Models\Region;
use App\Models\World;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HuntingSessionVisualUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
        CarbonImmutable::setTestNow('2026-07-12 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function zone(){return Zone::where('code','grey_oak_forest')->firstOrFail();}
    private function player(array $attributes=[]){return Character::factory()->selected()->for(User::factory())->create($attributes+['base_attack'=>500]);}

    public function test_victory_returns_matching_processed_hunt_reward_and_fresh_summary()
    {
        MonsterLootEntry::query()->update(['drop_probability_ppm'=>1000000,'minimum_quantity'=>1,'maximum_quantity'=>1]);
        $character=$this->player();$service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());
        $tick=$service->tick($character,$session)->toArray();
        $this->assertSame(CombatResultStatus::CHARACTER_VICTORY,$tick['processed_hunt']['status']);
        $this->assertSame($tick['processed_hunt']['hunt_id'],$tick['generated_reward']['hunt_id']);
        $this->assertSame(1,$tick['session_pending_rewards_summary']['rewards_count']);$this->assertSame(1,$tick['character_pending_rewards_summary']['rewards_count']);
        $this->assertNotNull($tick['inventory_capacity']);
    }

    public function test_early_and_timeout_ticks_have_no_processed_event_or_reward()
    {
        $character=$this->player();$service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());$first=$service->tick($character,$session)->toArray();$rewardCount=HuntReward::count();
        $early=$service->tick($character,$session)->toArray();
        $this->assertNull($early['processed_hunt']);$this->assertNull($early['generated_reward']);$this->assertSame($first['session_pending_rewards_summary'],$early['session_pending_rewards_summary']);$this->assertSame($first['character_pending_rewards_summary'],$early['character_pending_rewards_summary']);$this->assertSame($rewardCount,HuntReward::count());$this->assertNotNull($early['inventory_capacity']);
        $expired=HuntingSession::factory()->for($character)->create(['zone_id'=>$this->zone()->id,'last_heartbeat_at'=>CarbonImmutable::now()->subSeconds(31),'next_encounter_at'=>CarbonImmutable::now()->subHour()]);
        $timeout=$service->tick($character,$expired)->toArray();$this->assertSame(HuntingSessionStopReason::HEARTBEAT_TIMEOUT,$timeout['stop_reason']);$this->assertNull($timeout['processed_hunt']);$this->assertNull($timeout['generated_reward']);
    }

    public function test_empty_reward_is_returned_immediately_and_not_duplicated()
    {
        MonsterLootEntry::query()->update(['drop_probability_ppm'=>1]);$character=$this->player();$service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());$first=$service->tick($character,$session)->toArray();
        $this->assertSame(0,$first['generated_reward']['item_lines_count']);$this->assertSame([],$first['generated_reward']['items']);$this->assertSame(1,$first['character_pending_rewards_summary']['rewards_count']);
        $second=$service->tick($character,$session)->toArray();$this->assertNull($second['processed_hunt']);$this->assertNull($second['generated_reward']);$this->assertSame(1,HuntReward::count());
    }

    public function test_draw_and_defeat_never_return_generated_reward()
    {
        Monster::query()->update(['attack'=>0,'accuracy_rate'=>0,'max_health'=>1000000]);$drawCharacter=$this->player(['base_attack'=>0,'base_accuracy'=>0,'base_max_health'=>1000000,'current_health'=>1000000]);$service=app(HuntingSessionService::class);$drawSession=HuntingSession::find($service->start($drawCharacter,$this->zone())->id());$draw=$service->tick($drawCharacter,$drawSession)->toArray();$this->assertSame(CombatResultStatus::DRAW,$draw['processed_hunt']['status']);$this->assertNull($draw['generated_reward']);
        Monster::query()->update(['attack'=>10000,'accuracy_rate'=>100]);$defeated=$this->player(['current_health'=>1,'base_attack'=>0,'base_accuracy'=>0]);$defeatSession=HuntingSession::find($service->start($defeated,$this->zone())->id());$defeat=$service->tick($defeated,$defeatSession)->toArray();$this->assertSame(CombatResultStatus::MONSTER_VICTORY,$defeat['processed_hunt']['status']);$this->assertNull($defeat['generated_reward']);
    }

    public function test_show_is_historical_and_script_updates_safe_separate_blocks()
    {
        $character=$this->player();$service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());$service->tick($character,$session);$show=$service->show($character,$session)->toArray();$this->assertNotNull($show['latest_hunt']);$this->assertNull($show['processed_hunt']);$this->assertNull($show['generated_reward']);
        $response=$this->actingAs($character->user)->get(route('characters.hunting-sessions.show',[$character,$session]));
        $response->assertOk()->assertSee('function renderLatestHunt',false)->assertSee('function renderGeneratedReward',false)->assertSee('function renderPendingRewardsSummary',false)->assertSee('function renderInventoryCapacity',false)->assertSee('lastRenderedProcessedHuntId',false)->assertSee('Math.min(10000',false)->assertSee('document.createElement',false)->assertSee('textContent',false)->assertDontSee('innerHTML',false)->assertDontSee('insertAdjacentHTML',false);
        $response->assertSee('combat-log-scroll',false)->assertSee('height:31rem',false)->assertSee('overflow-y:auto',false)->assertSee('overflow-x:hidden',false)->assertSee('aria-live="polite"',false)->assertSee('.prepend(node)',false)->assertSee('scrollTop<=20',false)->assertSee('Nuevos eventos',false)->assertSee('combat-log-entry--critical',false)->assertSee('combat-log-entry--miss',false)->assertSee('combat-log-entry--result',false)->assertSee('combat-log-entry--loot',false);
        $response->assertSee('renderedHuntIds=new Set()',false)->assertSee('renderedEventsByHunt=new Map()',false)->assertSee('function ensureHuntBlock',false)->assertSee("block.dataset.huntId",false)->assertSee("document.getElementById('combat-log').prepend(block)",false)->assertSee("heading.textContent='Encuentro '+hunt.encounter_number",false)->assertSee('function renderInitialHistory',false)->assertSee('Hay eventos anteriores no mostrados',false)->assertDontSee("clearNode(document.getElementById('combat-log'))",false);
        $response->assertSee('renderInventoryItems(data.stackable_items,data.item_instances,data.inventory_status)',false)->assertSee("updateClaimButton(data.character_pending_rewards_summary,'idle')",false)->assertSee("button.textContent=requestState==='loading'?'Reclamando…':'Reclamar todas'",false);
        $response->assertSee('function completeCurrentPlaybackImmediately()',false)->assertSee("renderPlaybackAt(playback,Number(playback.hunt.playback_duration_ms))",false)->assertSee("stopRequested=true",false)->assertSee("if(requestInFlight||stopRequested)return",false)->assertSee("if(stopRequested)sendStop()",false)->assertSee("if(stopInFlight)return",false)->assertSee("event.preventDefault();requestStop(false)",false)->assertSee('Cacería detenida. Se conservaron todos los encuentros y recompensas obtenidos hasta este momento.',false);
        $response->assertSee('href="'.route('worlds.show',$this->zone()->region->world).'"',false)->assertDontSee('href="'.route('regions.show',$this->zone()->region).'"',false)->assertSee('← Volver a zonas')->assertSee('Detener y volver a zonas')->assertSee('Tu cacería sigue activa')->assertSee('Seguir cazando')->assertSee('Salir de todos modos')->assertSee("if(lastState.status!=='running')return",false)->assertSee('requestStop(true)',false)->assertSee('if(redirectAfterStop)window.location.assign(root.dataset.zonesUrl)',false)->assertSee('No se pudo confirmar la detención',false);
    }

    public function test_back_to_zones_uses_the_session_world_when_region_and_world_ids_differ()
    {
        $sourceWorld = World::where('code', 'eldoria')->firstOrFail();
        Region::create(['world_id' => $sourceWorld->id, 'code' => 'id-offset', 'name' => 'ID Offset', 'recommended_level_min' => 1, 'status' => 'active', 'sort_order' => 90]);
        Region::create(['world_id' => $sourceWorld->id, 'code' => 'id-offset-two', 'name' => 'ID Offset Two', 'recommended_level_min' => 1, 'status' => 'active', 'sort_order' => 91]);
        $targetWorld = World::create(['code' => 'navigation-target', 'name' => 'Navigation Target', 'status' => 'active', 'sort_order' => 90]);
        $targetRegion = Region::create(['world_id' => $targetWorld->id, 'code' => 'target-region', 'name' => 'Target Region', 'recommended_level_min' => 1, 'status' => 'active', 'sort_order' => 0]);
        $zone = $this->zone();
        $zone->update(['region_id' => $targetRegion->id]);
        $this->assertNotSame((int) $targetWorld->id, (int) $targetRegion->id);

        $character = $this->player();
        $session = HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character, $zone)->id());
        $response = $this->actingAs($character->user)->get(route('characters.hunting-sessions.show', [$character, $session]));

        $response->assertOk()
            ->assertSee('href="'.route('worlds.show', $targetWorld).'"', false)
            ->assertDontSee('href="'.route('regions.show', $targetRegion).'"', false);
    }

    public function test_victory_reward_remains_in_response_when_capacity_stops_same_tick()
    {
        MonsterLootEntry::query()->update(['drop_probability_ppm'=>1000000,'minimum_quantity'=>1,'maximum_quantity'=>1]);
        $character=$this->player();
        $single=Item::create(['code'=>'capacity_marker','name'=>'Capacity marker','item_type'=>'material','rarity'=>'common','is_stackable'=>true,'max_stack'=>2,'status'=>'active']);
        CharacterItem::create(['character_id'=>$character->id,'item_id'=>$single->id,'quantity'=>49,'locked_quantity'=>0]);
        $service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());$tick=$service->tick($character,$session)->toArray();
        $this->assertSame('stopped',$tick['status']);$this->assertSame(HuntingSessionStopReason::PENDING_INVENTORY_CAPACITY,$tick['stop_reason']);$this->assertNotNull($tick['processed_hunt']);$this->assertNotNull($tick['generated_reward']);$this->assertSame($tick['processed_hunt']['hunt_id'],$tick['generated_reward']['hunt_id']);
    }

    public function test_initial_history_is_limited_newest_first_and_ticks_do_not_include_it()
    {
        $character=$this->player();$service=app(HuntingSessionService::class);$session=HuntingSession::find($service->start($character,$this->zone())->id());
        $service->tick($character,$session);$source=Hunt::with(['enemies','combatEvents'])->firstOrFail();
        for($encounter=2;$encounter<=21;$encounter++){
            $copy=$source->replicate();$copy->save();
            foreach($source->enemies as $enemy){$enemyCopy=$enemy->replicate();$enemyCopy->hunt_id=$copy->id;$enemyCopy->save();}
            foreach($source->combatEvents as $event){$eventCopy=$event->replicate();$eventCopy->hunt_id=$copy->id;$eventCopy->save();}
        }
        $session->forceFill(['hunts_count'=>21,'victories_count'=>21,'next_encounter_at'=>CarbonImmutable::now()->addMinute()])->save();
        $service=app(HuntingSessionService::class);$show=$service->show($character,$session)->toArray();$history=$show['session_hunt_history'];
        $this->assertCount(20,$history['hunts']);$this->assertTrue($history['has_more']);$this->assertSame(20,$history['hunt_limit']);$this->assertSame(200,$history['event_limit']);
        $this->assertSame(21,$history['hunts'][0]['encounter_number']);$this->assertSame(2,$history['hunts'][19]['encounter_number']);
        $this->assertGreaterThan($history['hunts'][1]['hunt_id'],$history['hunts'][0]['hunt_id']);
        $this->assertLessThanOrEqual(200,array_sum(array_map(function($hunt){return count($hunt['events']);},$history['hunts'])));
        $session->forceFill(['next_encounter_at'=>CarbonImmutable::now()->addMinute()])->save();
        $early=$service->tick($character,$session)->toArray();$this->assertArrayNotHasKey('session_hunt_history',$early);$this->assertNull($early['processed_hunt']);
    }
}
