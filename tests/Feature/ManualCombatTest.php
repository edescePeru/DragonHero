<?php

namespace Tests\Feature;

use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Combat\CombatSide;
use App\Domain\Combat\Manual\CombatParticipantType;
use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Models\Character;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\Hunt;
use App\Models\HuntingSession;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualCombatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
    }

    private function player(array $attributes = [])
    {
        return Character::factory()->selected()->for(User::factory())->create($attributes);
    }

    private function huntingSession(Character $character)
    {
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        return HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character, $zone)->id());
    }

    public function test_owner_creates_idempotent_combat_participants_snapshots_and_state()
    {
        $character = $this->player(['current_health' => 25]);
        $session = $this->huntingSession($character);
        $response = $this->actingAs($character->user)->postJson(route('characters.manual-combats.store', [$character, $session]));

        $response->assertOk()->assertJsonPath('round', 1)->assertJsonPath('status', ManualCombatStatus::WAITING_PLAYER)->assertJsonPath('actions_available', ['basic_attack']);
        $this->assertSame(1, CombatSession::count());
        $combat = CombatSession::firstOrFail();
        $this->assertSame((int) $character->id, (int) $combat->active_slot);
        $this->assertSame($combat->participants()->count(), count($response->json('participants')));
        $player = $combat->participants()->where('participant_type', CombatParticipantType::CHARACTER)->firstOrFail();
        $expected = app(CharacterStatsCalculator::class)->calculate($character);
        $this->assertSame($expected->maxHealth(), $player->current_hp);
        $this->assertSame($expected->maxHealth(), $player->max_hp);
        $this->assertSame($expected->attack(), $player->stats_snapshot['attack']);
        $this->assertSame($player->id, $combat->current_participant_id);
        $this->assertSame(1, $player->initiative_position);

        $second = $this->actingAs($character->user)->postJson(route('characters.manual-combats.store', [$character, $session]));
        $second->assertOk()->assertJsonPath('combat_id', $combat->id);
        $this->assertSame(1, CombatSession::count());
    }

    public function test_repeated_monster_catalog_rows_create_independent_participants()
    {
        $character = $this->player();
        $session = $this->huntingSession($character);
        ZoneEncounterSize::where('zone_id', $session->zone_id)->delete();
        ZoneEncounterSize::create(['zone_id' => $session->zone_id, 'enemy_count' => 3, 'weight' => 100, 'is_active' => true, 'sort_order' => 1]);
        $zone = Zone::findOrFail($session->zone_id);
        $wolf = $zone->monsters()->where('monsters.code', 'grey_wolf')->firstOrFail();
        $zone->monsters()->where('monsters.id', '<>', $wolf->id)->get()->each(function ($monster) use ($zone) {
            $zone->monsters()->updateExistingPivot($monster->id, ['status' => 'inactive']);
        });

        app(ManualCombatCreationService::class)->create($character->user, $character, $session);
        $enemies = CombatParticipant::where('participant_type', CombatParticipantType::MONSTER)->orderBy('position')->get();
        $this->assertCount(3, $enemies);
        $this->assertSame([$wolf->id, $wolf->id, $wolf->id], $enemies->pluck('source_id')->all());
        $this->assertCount(3, $enemies->pluck('source_identifier')->unique());
        $this->assertSame([1, 2, 3], $enemies->pluck('position')->all());
    }

    public function test_equipment_is_frozen_and_later_source_changes_do_not_mutate_snapshots()
    {
        $character = $this->player();
        $item = Item::create(['code' => 'manual_weapon', 'name' => 'Manual weapon', 'item_type' => 'equipment', 'equipment_type' => 'weapon', 'rarity' => 'common', 'is_stackable' => false, 'max_stack' => 1, 'status' => 'active', 'attack_bonus' => 9]);
        $instance = ItemInstance::create(['uuid' => (string) Str::uuid(), 'character_id' => $character->id, 'item_id' => $item->id, 'refinement_level' => 0, 'status' => 'available', 'origin_type' => 'legacy_inventory', 'origin_id' => 91001, 'origin_unit_index' => 1, 'acquired_at' => now()]);
        app(CharacterEquipmentService::class)->equip($character, $instance->uuid, 'main_hand');
        $session = $this->huntingSession($character);
        app(ManualCombatCreationService::class)->create($character->user, $character, $session);
        $player = CombatParticipant::where('participant_type', CombatParticipantType::CHARACTER)->firstOrFail();
        $enemy = CombatParticipant::where('participant_type', CombatParticipantType::MONSTER)->firstOrFail();
        $attack = $player->stats_snapshot['attack'];
        $monsterAttack = $enemy->stats_snapshot['attack'];

        $character->update(['base_attack' => 999]);
        $enemy->source_id && \App\Models\Monster::whereKey($enemy->source_id)->update(['attack' => 999]);
        $this->assertSame(19, $attack);
        $this->assertSame($attack, $player->fresh()->stats_snapshot['attack']);
        $this->assertSame($monsterAttack, $enemy->fresh()->stats_snapshot['attack']);
        $this->assertNotEmpty($player->stats_snapshot['character_stats']['equipment_sources']);
    }

    public function test_security_invalid_session_read_scope_unique_slot_and_tick_coordination()
    {
        $character = $this->player();
        $session = $this->huntingSession($character);
        $state = app(ManualCombatCreationService::class)->create($character->user, $character, $session);
        $combat = CombatSession::findOrFail($state->id());
        app(HuntingSessionService::class)->tick($character, $session);
        $this->assertSame(0, Hunt::count());

        $other = $this->player();
        $this->actingAs($other->user)->getJson(route('characters.manual-combats.show', [$other, $combat]))->assertNotFound();
        $this->actingAs($character->user)->getJson(route('characters.manual-combats.show', [$character, $combat]))
            ->assertOk()->assertJsonMissing(['lock_version' => 0])->assertJsonMissing(['stats_snapshot' => $combat->participants->first()->stats_snapshot]);

        $stopped = HuntingSession::factory()->stopped()->for($other)->create(['zone_id' => $session->zone_id]);
        $this->actingAs($other->user)->postJson(route('characters.manual-combats.store', [$other, $stopped]))->assertStatus(422);

        $duplicate = $combat->replicate();
        $duplicate->hunting_session_id = $stopped->id;
        try {
            $duplicate->save();
            $this->fail('Duplicate active_slot should be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame(1, CombatSession::where('active_slot', $character->id)->count());
        }
    }

    public function test_schema_uses_mysql_compatible_unique_nullable_active_slot()
    {
        foreach (['combat_sessions', 'combat_participants'] as $tableName) {
            $table = \Illuminate\Support\Facades\DB::selectOne('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$tableName]);
            $this->assertSame('InnoDB', $table->ENGINE);
            $this->assertSame('utf8mb4_unicode_ci', $table->TABLE_COLLATION);
        }
        $column = \Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM combat_sessions WHERE Field = 'active_slot'");
        $this->assertSame('YES', $column->Null);
        $indexes = collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM combat_sessions WHERE Column_name = 'active_slot'"));
        $this->assertTrue($indexes->contains(function ($index) { return (int) $index->Non_unique === 0; }));

        $character = $this->player();
        $session = $this->huntingSession($character);
        app(ManualCombatCreationService::class)->create($character->user, $character, $session);
        $first = CombatSession::firstOrFail();
        $first->update(['status' => ManualCombatStatus::WON, 'active_slot' => null]);
        $replacement = $first->replicate();
        $replacement->status = ManualCombatStatus::PENDING;
        $replacement->active_slot = $character->id;
        $replacement->current_participant_id = null;
        $replacement->save();
        $this->assertSame(1, CombatSession::where('active_slot', $character->id)->count());
    }
}
