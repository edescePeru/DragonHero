<?php

namespace Tests\Feature;

use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Hunts\HuntService;
use App\Models\Character;
use App\Models\Hunt;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\RefinementStatModifier;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class RefinementStatIntegrationTest extends TestCase
{
    use RefreshDatabase;
    private $origin = 700000;
    private function createItemInstance(Character $character, $type, $level, $bonuses)
    {
        $item = Item::create(array_merge(['code' => 'integration_' . Str::random(8), 'name' => 'Integration item', 'item_type' => 'equipment', 'equipment_type' => $type, 'rarity' => 'common', 'is_stackable' => false, 'max_stack' => 1, 'status' => 'active'], $bonuses));
        return ItemInstance::create(['uuid' => (string) Str::uuid(), 'character_id' => $character->id, 'item_id' => $item->id, 'refinement_level' => $level, 'status' => 'available', 'origin_type' => 'legacy_inventory', 'origin_id' => $this->origin++, 'origin_unit_index' => 1, 'acquired_at' => now()]);
    }
    public function test_rings_keep_base_stats_but_do_not_receive_refinement_scaling()
    {
        RefinementStatModifier::create(['refinement_level' => 1, 'stat_increase_basis_points' => 1000, 'status' => 'active']);
        RefinementStatModifier::create(['refinement_level' => 3, 'stat_increase_basis_points' => 3000, 'status' => 'active']);
        $character = Character::factory()->for(User::factory())->create(['base_attack' => 10]);
        $left = $this->createItemInstance($character, 'ring', 1, ['attack_bonus' => 10]);
        $right = $this->createItemInstance($character, 'ring', 3, ['attack_bonus' => 10]);
        $equipment = app(CharacterEquipmentService::class);
        $equipment->equip($character, $left->uuid, 'ring_left');
        $equipment->equip($character, $right->uuid, 'ring_right');
        $stats = app(CharacterStatsCalculator::class)->breakdown($character);
        $this->assertSame(20, $stats->equipmentBase()->attack());
        $this->assertSame(0, $stats->refinement()->attack());
        $this->assertSame(20, $stats->equipment()->attack());
        $this->assertSame(30, $stats->effective()->attack());
    }
    public function test_global_modifier_changes_new_hunts_but_never_historical_snapshot()
    {
        $this->seed(WorldCatalogSeeder::class);
        $modifier = RefinementStatModifier::create(['refinement_level' => 3, 'stat_increase_basis_points' => 3000, 'status' => 'active']);
        $character = Character::factory()->for(User::factory())->create(['base_attack' => 10]);
        $instance = $this->createItemInstance($character, 'weapon', 3, ['attack_bonus' => 10]);
        app(CharacterEquipmentService::class)->equip($character, $instance->uuid, 'main_hand');
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $first = app(HuntService::class)->start($character, $zone);
        $firstSnapshot = Hunt::findOrFail($first->huntId())->character_stats_snapshot;
        $this->assertSame(23, $firstSnapshot['effective']['attack']);
        $modifier->update(['stat_increase_basis_points' => 5000]);
        $second = app(HuntService::class)->start($character, $zone);
        $this->assertSame(25, Hunt::findOrFail($second->huntId())->character_stats_snapshot['effective']['attack']);
        $this->assertSame($firstSnapshot, Hunt::findOrFail($first->huntId())->character_stats_snapshot);
    }
}
