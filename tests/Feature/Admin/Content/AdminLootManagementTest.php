<?php

namespace Tests\Feature\Admin\Content;

use App\Domain\Loot\LootGenerator;
use App\Models\Item;
use App\Models\Monster;
use App\Models\MonsterLootEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLootManagementTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['game_admin.emails' => ['loot-admin@example.test']]);
        $this->admin = User::factory()->create(['email' => 'loot-admin@example.test']);
        $this->actingAs($this->admin);
    }

    private function monster($code = 'loot_admin_monster')
    {
        return Monster::create(['code' => $code, 'name' => 'Loot Admin Monster', 'description' => null, 'level' => 1, 'max_health' => 10, 'attack' => 1, 'defense' => 1, 'accuracy_rate' => 80, 'evasion_rate' => 0, 'critical_chance' => 0, 'experience_reward' => 1, 'gold_min' => 0, 'gold_max' => 0, 'status' => 'active']);
    }

    private function item($code, $stackable = true)
    {
        return Item::create(['code' => $code, 'name' => $code, 'description' => null, 'item_type' => $stackable ? 'material' : 'equipment', 'equipment_type' => $stackable ? null : 'weapon', 'rarity' => 'common', 'is_stackable' => $stackable, 'max_stack' => $stackable ? 99 : 1, 'status' => 'active']);
    }

    private function requestPayload(Item $item, array $overrides = [])
    {
        return array_merge(['item_id' => $item->id, 'drop_probability_percent' => '70', 'minimum_quantity' => 1, 'maximum_quantity' => 2, 'status' => 'active', 'sort_order' => 3], $overrides);
    }

    private function entry(Item $item, Monster $monster, array $overrides = [])
    {
        return MonsterLootEntry::create(array_merge(['monster_id' => $monster->id, 'item_id' => $item->id, 'drop_probability_ppm' => 700000, 'minimum_quantity' => 1, 'maximum_quantity' => 2, 'status' => 'active', 'sort_order' => 3], $overrides));
    }

    public function test_monster_page_has_help_preloaded_editing_and_percentage()
    {
        $monster = $this->monster();
        $item = $this->item('help_item');
        $this->entry($item, $monster);

        $this->get(route('admin.content.monsters.show', $monster))
            ->assertOk()
            ->assertSee('Admite hasta 4 decimales.')
            ->assertSee('700000 PPM')
            ->assertSee('70.0000 %')
            ->assertSee('Guardar cambios')
            ->assertSee('value="70.0000"', false)
            ->assertSee('Editar')
            ->assertSee('Desactivar');
    }

    public function test_general_update_changes_item_values_and_state_without_duplicate()
    {
        $monster = $this->monster();
        $old = $this->item('old_drop');
        $new = $this->item('new_drop');
        $entry = $this->entry($old, $monster);

        $this->put(route('admin.content.monsters.loot.update', [$monster, $entry]), $this->requestPayload($new, ['drop_probability_percent' => '50', 'minimum_quantity' => 4, 'maximum_quantity' => 7, 'status' => 'inactive', 'sort_order' => 9]))
            ->assertRedirect(route('admin.content.monsters.show', $monster))
            ->assertSessionHas('status', 'Loot actualizado correctamente.');

        $this->assertSame(1, MonsterLootEntry::count());
        $entry = $entry->fresh();
        $this->assertSame($new->id, $entry->item_id);
        $this->assertSame(500000, $entry->drop_probability_ppm);
        $this->assertSame(4, $entry->minimum_quantity);
        $this->assertSame(7, $entry->maximum_quantity);
        $this->assertSame(9, $entry->sort_order);
        $this->assertSame('inactive', $entry->status);

        $duplicate = $this->entry($old, $monster);
        $this->put(route('admin.content.monsters.loot.update', [$monster, $entry]), $this->requestPayload($old))->assertSessionHasErrors('item_id');
        $this->assertSame($new->id, $entry->fresh()->item_id);
        $other = $this->monster('other_monster');
        $this->put(route('admin.content.monsters.loot.update', [$other, $duplicate]), $this->requestPayload($old))->assertNotFound();
    }

    public function test_explicit_status_actions_preserve_fields_and_reactivation_restores_generator()
    {
        $monster = $this->monster();
        $item = $this->item('status_drop');
        $entry = $this->entry($item, $monster, ['drop_probability_ppm' => 1000000, 'minimum_quantity' => 2, 'maximum_quantity' => 2, 'sort_order' => 8]);
        $fields = ['item_id', 'drop_probability_ppm', 'minimum_quantity', 'maximum_quantity', 'sort_order'];
        $before = $entry->only($fields);

        $this->patch(route('admin.content.monsters.loot.deactivate', [$monster, $entry]))->assertSessionHas('status', 'Loot desactivado correctamente.');
        $entry = $entry->fresh();
        $this->assertSame('inactive', $entry->status);
        $this->assertSame($before, $entry->only($fields));
        $this->assertCount(0, app(LootGenerator::class)->generateFor($monster)->drops());

        $this->patch(route('admin.content.monsters.loot.activate', [$monster, $entry]))->assertSessionHas('status', 'Loot activado correctamente.');
        $entry = $entry->fresh();
        $this->assertSame('active', $entry->status);
        $this->assertSame($before, $entry->only($fields));
        $this->assertCount(1, app(LootGenerator::class)->generateFor($monster)->drops());
    }

    public function test_non_stackable_quantities_are_rejected_by_request()
    {
        $monster = $this->monster();
        $unique = $this->item('unique_drop', false);
        $this->post(route('admin.content.monsters.loot.store', $monster), $this->requestPayload($unique, ['minimum_quantity' => 1, 'maximum_quantity' => 2]))->assertSessionHasErrors('maximum_quantity');
        $this->assertDatabaseMissing('monster_loot_entries', ['monster_id' => $monster->id, 'item_id' => $unique->id]);
    }

    public function test_percentage_precision_and_invalid_formats()
    {
        $monster = $this->monster();
        $valid = [['0', 0], ['0.0001', 1], ['0.0123', 123], ['1', 10000], ['12.3456', 123456], ['99.9999', 999999], ['100', 1000000], ['100.0000', 1000000]];

        foreach ($valid as $case) {
            list($percentage, $ppm) = $case;
            $item = $this->item('valid_'.str_replace('.', '_', $percentage));
            $this->post(route('admin.content.monsters.loot.store', $monster), $this->requestPayload($item, ['drop_probability_percent' => $percentage]))->assertSessionDoesntHaveErrors();
            $this->assertDatabaseHas('monster_loot_entries', ['monster_id' => $monster->id, 'item_id' => $item->id, 'drop_probability_ppm' => $ppm]);
        }

        foreach (['', '-1', '100.0001', '1.23456', '01', '1e-4', 'texto'] as $index => $percentage) {
            $item = $this->item('invalid_'.$index);
            $this->post(route('admin.content.monsters.loot.store', $monster), $this->requestPayload($item, ['drop_probability_percent' => $percentage]))->assertSessionHasErrors('drop_probability_percent');
            $this->assertDatabaseMissing('monster_loot_entries', ['monster_id' => $monster->id, 'item_id' => $item->id]);
        }
    }

    public function test_create_and_edit_preserve_fine_ppm_without_double_conversion()
    {
        $monster = $this->monster();
        $item = $this->item('fine_probability');

        $this->post(route('admin.content.monsters.loot.store', $monster), $this->requestPayload($item, ['drop_probability_percent' => '0.0050']))
            ->assertRedirect();
        $entry = MonsterLootEntry::where('monster_id', $monster->id)->where('item_id', $item->id)->firstOrFail();
        $this->assertSame(50, $entry->drop_probability_ppm);
        $this->get(route('admin.content.monsters.show', $monster))->assertSee('0.0050 %')->assertDontSee('5.0E');

        $this->put(route('admin.content.monsters.loot.update', [$monster, $entry]), $this->requestPayload($item, ['drop_probability_percent' => '0.0001']))
            ->assertRedirect();
        $this->assertSame(1, $entry->fresh()->drop_probability_ppm);
    }
}
