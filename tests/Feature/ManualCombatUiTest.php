<?php

namespace Tests\Feature;

use App\Domain\Combat\Manual\ManualCombatCreationService;
use App\Domain\Combat\Manual\ManualCombatPresentationService;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Media\MediaAssetType;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Models\Character;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use App\Models\Item;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualCombatUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
    }

    private function context()
    {
        $character = Character::factory()->selected()->for(User::factory())->create();
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $hunting = HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character, $zone)->id());
        $state = app(ManualCombatCreationService::class)->create($character->user, $character, $hunting);
        return [$character, $hunting, CombatSession::findOrFail($state->id())];
    }

    public function test_play_route_requires_authentication_and_rejects_foreign_context()
    {
        list($character, $hunting, $combat) = $this->context();
        $this->get(route('characters.manual-combats.play', [$character, $combat]))->assertRedirect(route('login'));

        $foreign = Character::factory()->selected()->for(User::factory())->create();
        $this->actingAs($foreign->user)->get(route('characters.manual-combats.play', [$foreign, $combat]))->assertNotFound();
        $this->actingAs($foreign->user)->get(route('characters.manual-combats.play', [$character, $combat]))->assertForbidden();
    }

    public function test_owner_opens_functional_blade_with_authoritative_urls_and_assets()
    {
        list($character, $hunting, $combat) = $this->context();
        $response = $this->actingAs($character->user)->get(route('characters.manual-combats.play', [$character, $combat]));
        $decodedContent = str_replace('\\/', '/', $response->getContent());

        $response->assertOk()
            ->assertSee('id="manual-combat-page"', false)
            ->assertSee('assets/js/manual-combat.js', false)
            ->assertSee('assets/css/manual-combat.css', false)
            ->assertSee('Ataque básico');
        $this->assertStringContainsString(route('characters.manual-combats.show', [$character, $combat]), $decodedContent);
        $this->assertStringContainsString(route('characters.manual-combats.actions.store', [$character, $combat]), $decodedContent);
        $this->assertStringContainsString(route('characters.manual-combats.rewards.claim', [$character, $combat]), $decodedContent);
        $this->assertStringContainsString(route('characters.manual-combats.abandon', [$character, $combat]), $decodedContent);
        $returnUrl = route('worlds.regions.show', [$combat->zone->region->world, $combat->zone->region]);
        $this->assertStringContainsString($returnUrl, $decodedContent);
        $this->assertStringNotContainsString(route('characters.hunting-sessions.tick', [$character, $hunting]), $decodedContent);
        $this->assertStringNotContainsString(route('characters.hunting-sessions.show', [$character, $hunting]), $decodedContent);
        $response->assertSee('class="manual-combat-layout"', false)
            ->assertSee('class="manual-combat-stage', false)
            ->assertSee('manual-combat-stage__participants', false)
            ->assertSee('manual-combat-actions', false)
            ->assertSee('Equipamiento actual')
            ->assertSee('Inventario')
            ->assertSee('Estadísticas del combate')
            ->assertSee('Loot encontrado')
            ->assertSee('data-select-target=', false)
            ->assertDontSee('characters.equipment.equip', false)
            ->assertDontSee('characters.equipment.unequip', false)
            ->assertDontSee('Usar objeto')
            ->assertDontSee('Defender')
            ->assertDontSee('Skill')
            ->assertDontSee('Escapar');
        $css = file_get_contents(public_path('assets/css/manual-combat.css'));
        $this->assertStringContainsString('grid-template-columns:minmax(0,2fr) minmax(20rem,1fr)', $css);
        $this->assertStringContainsString('@media(max-width:1199.98px)', $css);
        $this->assertStringContainsString('.manual-combat-log{height:31rem;overflow-y:auto;overflow-x:hidden', $css);
    }

    public function test_terminal_combat_screen_loads_without_enabling_server_actions()
    {
        list($character, $hunting, $combat) = $this->context();
        $combat->update(['status' => ManualCombatStatus::WON, 'current_participant_id' => null, 'active_slot' => null, 'completed_at' => now()]);

        $this->actingAs($character->user)
            ->get(route('characters.manual-combats.play', [$character, $combat]))
            ->assertOk()
            ->assertSee('manual-combat-terminal', false)
            ->assertSee('Reclamar recompensas');
    }

    public function test_automatic_view_excludes_manual_controls_and_zone_entry_reuses_existing_combat()
    {
        list($character, $hunting, $combat) = $this->context();
        $this->actingAs($character->user)
            ->get(route('characters.hunting-sessions.show', [$character, $hunting]))
            ->assertOk()
            ->assertDontSee('id="start-manual-combat"', false)
            ->assertDontSee(route('characters.manual-combats.store', [$character, $hunting]), false)
            ->assertSee('id="retry-hunting-polling"', false)
            ->assertSee('networkRetryDelays=[2000,5000]', false)
            ->assertSee("error.status===500", false);

        $before = CombatSession::count();
        $response = $this->postJson(route('characters.manual-combats.zones.store', [$character, $combat->zone]));
        $response->assertOk()
            ->assertJsonPath('combat_id', $combat->id)
            ->assertJsonPath('play_url', route('characters.manual-combats.play', [$character, $combat]));
        $this->assertSame($before, CombatSession::count());
    }

    public function test_zone_manual_entry_stops_automatic_flow_and_redirects_directly_to_play()
    {
        $character = Character::factory()->selected()->for(User::factory())->create();
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $automatic = HuntingSession::findOrFail(app(HuntingSessionService::class)->start($character, $zone)->id());

        $response = $this->actingAs($character->user)->post(route('characters.manual-combats.zones.store', [$character, $zone]));
        $combat = CombatSession::firstOrFail();

        $response->assertRedirect(route('characters.manual-combats.play', [$character, $combat]));
        $this->assertSame('stopped', $automatic->fresh()->status);
        $this->assertSame(1, CombatSession::count());
    }

    public function test_manual_combat_log_never_adds_an_empty_class_and_recovers_after_local_render_error()
    {
        $javascript = file_get_contents(public_path('assets/js/manual-combat.js'));

        $this->assertStringNotContainsString("classList.add(event.payload.targets[0].critical ?", $javascript);
        $this->assertStringNotContainsString("classList.add('')", $javascript);
        $this->assertStringContainsString("if (target.critical) row.classList.add('manual-combat-log__entry--critical')", $javascript);
        $this->assertStringContainsString("if (!target.hit) row.classList.add('manual-combat-log__entry--miss')", $javascript);
        $this->assertStringContainsString('El ataque fue procesado, pero la pantalla tuvo que actualizarse.', $javascript);
        $this->assertStringContainsString('loadCombatState(false)', $javascript);
        $this->assertStringContainsString("byId('manual-combat-log').prepend(row)", $javascript);
        $this->assertStringNotContainsString("byId('manual-combat-log').appendChild(row)", $javascript);
        $this->assertStringContainsString('lastEventSequence = Math.max(lastEventSequence, Number(event.sequence))', $javascript);
    }

    public function test_manual_layout_uses_zone_background_and_frozen_combat_stats()
    {
        list($character, $hunting, $combat) = $this->context();
        $combat->zone->mediaAssets()->create(['asset_type' => MediaAssetType::BACKGROUND, 'disk' => 'public', 'path' => 'zones/manual-background.webp', 'is_primary' => true]);
        $participant = $combat->participants()->where('participant_type', 'character')->firstOrFail();
        $snapshot = $participant->stats_snapshot;
        $snapshot['attack'] = 321;
        $snapshot['defense'] = 123;
        $participant->update(['stats_snapshot' => $snapshot]);
        $character->update(['base_attack' => 9999, 'base_defense' => 9999]);

        $response = $this->actingAs($character->user)->get(route('characters.manual-combats.play', [$character, $combat]));

        $response->assertOk()
            ->assertSee('zones/manual-background.webp', false)
            ->assertSee('<span>Ataque</span><strong>321</strong>', false)
            ->assertSee('<span>Defensa</span><strong>123</strong>', false)
            ->assertDontSee('<span>Ataque</span><strong>9999</strong>', false)
            ->assertDontSee('Bono de loot')
            ->assertDontSee('Bono de oro');
    }

    public function test_manual_reward_icons_are_decorated_with_one_grouped_item_query()
    {
        $item = Item::create(['code' => 'manual_loot_icon', 'name' => 'Manual loot icon', 'item_type' => 'material', 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => 99, 'status' => 'active']);
        $item->mediaAssets()->create(['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => 'items/manual-loot.webp', 'is_primary' => true]);

        $decorated = app(ManualCombatPresentationService::class)->decorateRewards([
            'status' => 'pending', 'gold' => 3, 'experience' => 5,
            'items' => [['item_id' => $item->id, 'name' => $item->name, 'quantity' => 2]],
            'claim_available' => false,
        ]);

        $this->assertStringContainsString('items/manual-loot.webp', $decorated['items'][0]['image_url']);
    }

    public function test_manual_inventory_renders_one_card_per_authoritative_stack_quantity()
    {
        list($character, $hunting, $combat) = $this->context();
        $item = Item::create(['code' => 'manual_stack_cards', 'name' => 'Cuero desgastado', 'item_type' => 'material', 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => 99, 'status' => 'active']);
        \App\Models\CharacterItem::create(['character_id' => $character->id, 'item_id' => $item->id, 'quantity' => 103, 'locked_quantity' => 0]);

        $response = $this->actingAs($character->user)->get(route('characters.manual-combats.play', [$character, $combat]));
        $response->assertOk()->assertSee('×99')->assertSee('×4')->assertDontSee('×103');
        $this->assertSame(2, substr_count($response->getContent(), 'data-item-id="'.$item->id.'"'));
    }
}
