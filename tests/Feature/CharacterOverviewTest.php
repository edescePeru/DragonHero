<?php

namespace Tests\Feature;

use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Equipment\CharacterEquipmentSlot;
use App\Domain\Inventory\Instances\ItemInstanceStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CharacterLevelRequirementSeeder::class);
    }

    public function test_overview_requires_authentication_and_ownership()
    {
        $character = Character::factory()->create();

        $this->get(route('characters.overview', $character))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('characters.overview', $character))->assertForbidden();
        $this->actingAs($character->user)->get(route('characters.overview', $character))->assertOk();
    }

    public function test_overview_renders_authoritative_summary_capacity_and_accessible_actions()
    {
        $character = Character::factory()->create(['level' => 2, 'experience' => 140]);
        $material = $this->stackableItem('overview_material', 'Mineral de prueba', 5);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $material->id, 'quantity' => 12, 'locked_quantity' => 2]);
        $weapon = $this->uniqueItem('overview_weapon', 'Espada de prueba');
        $instance = $this->createItemInstance($character, $weapon);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));

        $response->assertOk()
            ->assertSee('Vista compacta')
            ->assertSee('EXP 140')
            ->assertSee('Faltan 110')
            ->assertSee('Usados 4 de 30')
            ->assertSee('Mineral de prueba')
            ->assertSee('×12')
            ->assertSee('Espada de prueba')
            ->assertSee('data-overview-open-panel', false)
            ->assertSee(route('characters.equipment.equip', $character), false)
            ->assertSee('Estadísticas efectivas')
            ->assertDontSee(route('characters.item-instances.refine', [$character, $instance]), false);
    }

    public function test_equipped_item_uses_existing_unequip_endpoint_and_all_eight_slots_are_present()
    {
        $character = Character::factory()->create();
        $weapon = $this->uniqueItem('equipped_overview', 'Arma equipada');
        $instance = $this->createItemInstance($character, $weapon);
        app(CharacterEquipmentService::class)->equip($character, $instance->uuid, CharacterEquipmentSlot::WEAPON_MAIN);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));

        $response->assertOk()
            ->assertSee('Arma equipada +0')
            ->assertSee(route('characters.equipment.unequip', $character), false);
        foreach (CharacterEquipmentSlot::all() as $slot) {
            $response->assertSee(CharacterEquipmentSlot::label($slot));
        }
    }

    public function test_item_media_loading_is_bounded_instead_of_one_query_per_item()
    {
        $character = Character::factory()->create();
        for ($index = 1; $index <= 8; $index++) {
            $item = $this->stackableItem('bounded_'.$index, 'Material '.$index, 99);
            CharacterItem::create(['character_id' => $character->id, 'item_id' => $item->id, 'quantity' => 1, 'locked_quantity' => 0]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($character->user)->get(route('characters.overview', $character))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(24, $queryCount, 'La vista compacta ejecutó demasiadas consultas: '.$queryCount);
    }

    public function test_frontend_contract_is_responsive_and_uses_safe_dom_updates()
    {
        $styles = file_get_contents(base_path('src/assets/scss/_character-overview.scss'));
        $script = file_get_contents(base_path('src/assets/js/character-overview.js'));

        $this->assertStringContainsString('repeat(10', $styles);
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $styles);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $styles);
        $this->assertStringContainsString('@media (max-width: 399.98px)', $styles);
        $this->assertStringContainsString('replaceChildren', $script);
        $this->assertStringContainsString("Accept: 'application/json'", $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    private function stackableItem($code, $name, $maxStack)
    {
        return Item::create(['code' => $code, 'name' => $name, 'item_type' => 'material', 'equipment_type' => null, 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => $maxStack, 'status' => 'active']);
    }

    private function uniqueItem($code, $name)
    {
        return Item::create(['code' => $code, 'name' => $name, 'item_type' => 'equipment', 'equipment_type' => 'weapon', 'rarity' => 'common', 'is_stackable' => false, 'max_stack' => 1, 'required_level' => 1, 'status' => 'active']);
    }

    private function createItemInstance(Character $character, Item $item)
    {
        return ItemInstance::create(['uuid' => (string) Str::uuid(), 'character_id' => $character->id, 'item_id' => $item->id, 'refinement_level' => 0, 'status' => ItemInstanceStatus::AVAILABLE, 'origin_type' => 'legacy_inventory', 'origin_id' => random_int(10000, 99999), 'origin_unit_index' => 1, 'acquired_at' => now()]);
    }
}
