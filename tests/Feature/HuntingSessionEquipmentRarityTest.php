<?php

namespace Tests\Feature;

use App\Domain\Characters\Overview\CharacterOverviewService;
use App\Domain\Equipment\CharacterEquipmentSlot;
use App\Domain\Hunts\Sessions\HuntingSessionPresentationService;
use App\Domain\Inventory\Instances\ItemInstanceStatus;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\ItemRarity;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HuntingSessionEquipmentRarityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
        $this->seed(CharacterLevelRequirementSeeder::class);
    }

    public function test_hunting_equipment_reuses_authoritative_rarity_contract_for_all_rarities()
    {
        $character = Character::factory()->selected()->for(User::factory())->create();
        $definitions = [
            ['common', CharacterEquipmentSlot::MAIN_HAND, 'weapon'],
            ['rare', CharacterEquipmentSlot::HELMET, 'helmet'],
            ['mythic', CharacterEquipmentSlot::ARMOR, 'armor'],
            ['legendary', CharacterEquipmentSlot::GLOVES, 'gloves'],
        ];
        $instances = [];

        foreach ($definitions as $index => $definition) {
            $instances[$definition[0]] = $this->equip(
                $character,
                $definition[0],
                $definition[1],
                $definition[2],
                $index + 1
            );
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $presentation = app(HuntingSessionPresentationService::class)->prepare(
            $character,
            Zone::where('code', 'grey_oak_forest')->firstOrFail()
        );
        $occupied = collect($presentation['equipment'])->where('occupied', true)->keyBy('rarity_code');
        $empty = collect($presentation['equipment'])->firstWhere('slot', CharacterEquipmentSlot::BOOTS);

        foreach (['common' => 'Común', 'rare' => 'Raro', 'mythic' => 'Mítico', 'legendary' => 'Legendario'] as $code => $name) {
            $slot = $occupied->get($code);
            $this->assertNotNull($slot);
            $this->assertSame($name, $slot['rarity_name']);
            $this->assertSame($instances[$code]->uuid, $slot['item_instance_uuid']);
            $this->assertNotEmpty($slot['public_reference']);
            $this->assertCount(7, $slot['css_variables']);
            $this->assertNotEmpty($slot['rarity_visual_style_attribute']);
            $this->assertArrayHasKey('base_bonuses', $slot);
            $this->assertArrayHasKey('refinement_bonuses', $slot);
            $this->assertArrayHasKey('rarity_bonuses', $slot);
            $this->assertArrayHasKey('total_bonuses', $slot);
        }

        $this->assertFalse($empty['occupied']);
        $this->assertSame('Botas', $empty['slot_label']);
        $this->assertArrayNotHasKey('css_variables', $empty);
        $this->assertLessThan(count($occupied), collect($queries)->filter(function ($sql) {
            return strpos($sql, 'from `item_rarities`') !== false;
        })->count());
        $this->assertLessThan(count($occupied), collect($queries)->filter(function ($sql) {
            return strpos($sql, 'from `media_assets`') !== false;
        })->count());

        $html = view('components.hunting.equipment-summary', [
            'equipment' => $presentation['equipment'],
        ])->render();
        $this->assertSame(4, substr_count($html, 'item-rarity-visual'));
        $this->assertStringContainsString('Rareza: Legendario', $html);
        $this->assertStringContainsString('Rareza: Raro', $html);
        $this->assertStringContainsString('Rareza: Mítico', $html);
        $this->assertStringContainsString('Rareza: Común', $html);
        $this->assertStringContainsString('Instancia #'.$occupied->get('legendary')['public_reference'], $html);
        $this->assertStringContainsString('data-equipment-slot="boots"', $html);
    }

    public function test_hunting_and_overview_share_custom_visual_reference_refinement_and_bonuses()
    {
        $character = Character::factory()->selected()->for(User::factory())->create();
        $legendary = ItemRarity::where('code', 'legendary')->firstOrFail();
        $legendary->update([
            'border_color_hex' => '#123456',
            'inner_glow_color_hex' => '#654321',
        ]);
        $instance = $this->equip(
            $character,
            'legendary',
            CharacterEquipmentSlot::MAIN_HAND,
            'weapon',
            3,
            ['rarity' => 'common', 'attack_bonus' => 20, 'allows_refinement' => true, 'refinement_stat' => 'attack']
        );

        $hunting = app(HuntingSessionPresentationService::class)->prepare(
            $character,
            Zone::where('code', 'grey_oak_forest')->firstOrFail()
        );
        $overview = app(CharacterOverviewService::class)->snapshot($character);
        $huntingSlot = collect($hunting['equipment'])->firstWhere('item_instance_uuid', $instance->uuid);
        $overviewSlot = collect($overview['equipment'])->firstWhere('item_instance_uuid', $instance->uuid);

        $this->assertSame('Legendario', $huntingSlot['rarity_name']);
        $this->assertSame($overviewSlot['rarity_name'], $huntingSlot['rarity_name']);
        $this->assertSame($overviewSlot['public_reference'], $huntingSlot['public_reference']);
        $this->assertSame($overviewSlot['refinement_level'], $huntingSlot['refinement_level']);
        $this->assertSame($overviewSlot['css_variables'], $huntingSlot['css_variables']);
        $this->assertSame($overviewSlot['total_bonuses'], $huntingSlot['total_bonuses']);
        $this->assertSame('18, 52, 86', $huntingSlot['css_variables']['--rarity-border-rgb']);
        $this->assertSame('101, 67, 33', $huntingSlot['css_variables']['--rarity-glow-rgb']);

        $response = $this->actingAs($character->user)->get(route(
            'characters.hunting-sessions.show',
            [$character, app(\App\Domain\Hunts\Sessions\HuntingSessionService::class)->start(
                $character,
                Zone::where('code', 'grey_oak_forest')->firstOrFail()
            )->id()]
        ));
        $response->assertOk()
            ->assertSee('item-rarity-visual', false)
            ->assertSee('Rareza: Legendario')
            ->assertSee('Instancia #'.$huntingSlot['public_reference'])
            ->assertSee('--rarity-border-rgb:18, 52, 86', false);
    }

    private function equip(Character $character, $rarityCode, $slot, $equipmentType, $refinementLevel, array $itemOverrides = [])
    {
        $rarity = ItemRarity::where('code', $rarityCode)->firstOrFail();
        $item = Item::create(array_merge([
            'code' => 'hunting-equipment-'.Str::random(12),
            'name' => ucfirst($rarityCode).' equipment',
            'item_type' => 'equipment',
            'equipment_type' => $equipmentType,
            'rarity' => $rarityCode === 'legendary' ? 'common' : 'legendary',
            'is_stackable' => false,
            'max_stack' => 1,
            'required_level' => 1,
            'status' => 'active',
        ], $itemOverrides));
        $item->allowedRarities()->sync([$rarity->id]);
        $instance = ItemInstance::create([
            'uuid' => (string) Str::uuid(),
            'character_id' => $character->id,
            'item_id' => $item->id,
            'item_rarity_id' => $rarity->id,
            'refinement_level' => $refinementLevel,
            'status' => ItemInstanceStatus::EQUIPPED,
            'origin_type' => 'legacy_inventory',
            'origin_id' => random_int(100000, 999999),
            'origin_unit_index' => 1,
            'acquired_at' => now(),
        ]);
        CharacterEquipment::create([
            'character_id' => $character->id,
            'slot' => $slot,
            'item_instance_id' => $instance->id,
            'equipped_at' => now(),
        ]);

        return $instance;
    }
}
