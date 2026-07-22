<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopSalePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buying_shop_renders_ssr_stacks_instances_and_safe_sale_contract()
    {
        $character = Character::factory()->selectedFor($user = User::factory()->create())->create();
        $stack = $this->item('sale-ui-stack', true);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $stack->id, 'quantity' => 10, 'locked_quantity' => 3]);
        $unique = $this->item('sale-ui-instance', false);
        $available = ItemInstance::factory()->create(['character_id' => $character->id, 'item_id' => $unique->id, 'refinement_level' => 3]);
        $equipped = ItemInstance::factory()->equipped()->create(['character_id' => $character->id, 'item_id' => $unique->id]);
        CharacterEquipment::create(['character_id' => $character->id, 'slot' => 'main_hand', 'item_instance_id' => $equipped->id, 'equipped_at' => now()]);
        $sold = ItemInstance::factory()->sold()->create(['character_id' => $character->id, 'item_id' => $unique->id]);
        $shop = Shop::factory()->create(['buys_items' => true, 'purchase_rate_basis_points' => 5000]);

        $response = $this->actingAs($user)->get(route('characters.shops.show', [$character, $shop]));
        $response->assertOk()->assertSee('id="shop-sell-tab"', false)->assertSee('data-shop-sale-catalog', false)
            ->assertSee('data-current-gold=', false)->assertSee('data-shop-gold', false)
            ->assertSee('sale-ui-stack')->assertSee('Disponibles')->assertSee('data-sale-available>7</span> de <span data-sale-total>10</span>', false)->assertSee('3</dd>', false)
            ->assertSee('max="7"', false)->assertSee('sale-ui-instance')->assertSee('Refinamiento +3')
            ->assertSee('Debes desequipar este objeto antes de venderlo.')->assertSee('id="shop-sale-modal"', false)
            ->assertSee('type="button" class="btn btn-primary" data-shop-sale-confirm', false)
            ->assertSee('aria-live="polite" data-shop-sale-feedback', false)
            ->assertSee(route('characters.shops.sales.store', [$character, $shop]), false)
            ->assertSee('data-character-item-id=', false)->assertSee('data-item-instance-uuid=', false)
            ->assertDontSee($sold->uuid)->assertDontSee('data-item-id=', false)->assertDontSee('data-sell-price=', false);
        $this->assertStringContainsString($available->uuid, $response->getContent());
        preg_match('/<div class="modal fade" id="shop-sale-modal".*?<\/section>/s', $response->getContent(), $saleModal);
        $this->assertNotEmpty($saleModal);
        $this->assertStringNotContainsString('<form', $saleModal[0]);
    }

    public function test_non_buying_shop_does_not_render_sale_ui_or_foreign_inventory()
    {
        $character = Character::factory()->selectedFor($user = User::factory()->create())->create();
        $foreign = Character::factory()->create();
        $item = $this->item('foreign-sale-ui', true);
        CharacterItem::create(['character_id' => $foreign->id, 'item_id' => $item->id, 'quantity' => 2, 'locked_quantity' => 0]);
        $shop = Shop::factory()->create(['buys_items' => false]);
        $this->actingAs($user)->get(route('characters.shops.show', [$character, $shop]))->assertOk()
            ->assertDontSee('id="shop-sell-tab"', false)->assertDontSee('data-shop-sale-catalog', false)->assertDontSee('foreign-sale-ui');
    }

    public function test_sale_javascript_contract_is_separate_safe_and_imported()
    {
        $source = file_get_contents(base_path('src/assets/js/shop-sale.js'));
        $main = file_get_contents(base_path('src/assets/js/main.js'));
        $this->assertStringContainsString("import './shop-sale.js';", $main);
        foreach (['[data-shop-sale-catalog]', 'bootstrap.Modal.getOrCreateInstance', 'source_type', 'character_item_id', 'item_instance_uuid', 'quantity', 'zone_id', 'idempotency_key', 'crypto.randomUUID', 'response.status === 409', 'response.status === 404', 'data.current_gold', 'data.total_gold', 'data.remaining_quantity', 'data.item_removed', 'pageRoot.dataset.currentGold', "pageRoot.querySelectorAll('[data-shop-gold]')", 'modal.hide()', 'Venta realizada correctamente.', 'Recibiste', 'const submittedOperation = operation', 'const submittedEntry = submittedOperation.entry', "const submittedButton = submittedEntry.querySelector('[data-shop-sale-button]')", 'complete(payload.data, submittedOperation)', 'requestInFlight = false', 'confirmButton.disabled = false', 'submittedEntry.isConnected', 'submittedButton.isConnected', "submittedEntry.dataset.canSell === '1'", 'availableQuantity > 0', 'submittedButton.disabled = false', 'operation = null'] as $needle) $this->assertStringContainsString($needle, $source);
        foreach (["document.querySelectorAll('[data-current-gold],[data-shop-gold]')", "root.querySelectorAll('[data-shop-sale-button]')", 'operation.entry.isConnected', 'root.textContent', 'pageRoot.textContent', 'root.innerHTML', 'pageRoot.innerHTML', 'window.location', 'location.href', 'form.submit()', 'sell_price', 'item_id:', 'unit_gold:', 'total_gold:', 'innerHTML'] as $needle) $this->assertStringNotContainsString($needle, $source);
    }

    private function item($code, $stackable)
    {
        return Item::create(['code' => $code, 'name' => $code, 'item_type' => $stackable ? 'material' : 'equipment', 'equipment_type' => $stackable ? null : 'weapon', 'rarity' => 'common', 'is_stackable' => $stackable, 'max_stack' => $stackable ? 99 : 1, 'is_sellable' => true, 'sell_price' => 10, 'status' => 'active']);
    }
}
