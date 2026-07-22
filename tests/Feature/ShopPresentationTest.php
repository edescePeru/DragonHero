<?php

namespace Tests\Feature;

use App\Domain\Shops\ShopLocationType;
use App\Domain\Shops\ShopOfferCategory;
use App\Domain\Shops\ShopOfferVisibility;
use App\Domain\Shops\ShopReadService;
use App\Models\Character;
use App\Models\CharacterWallet;
use App\Models\Item;
use App\Models\Shop;
use App\Models\ShopLocation;
use App\Models\ShopOffer;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function offer(Shop $shop, $visibility = ShopOfferVisibility::VISIBLE)
    {
        $item = Item::create(['code' => 'shop-ui-'.$visibility, 'name' => $visibility === ShopOfferVisibility::VISIBLE ? 'Poción del bosque' : 'Objeto oculto', 'item_type' => 'consumable', 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => 10, 'status' => 'active', 'required_level' => 1]);

        return ShopOffer::create(['shop_id' => $shop->id, 'item_id' => $item->id, 'category' => ShopOfferCategory::CONSUMABLES, 'quantity' => 5, 'gold_price' => 25, 'stock_limit' => 10, 'stock_remaining' => 10, 'purchase_limit_per_character' => 3, 'required_character_level' => 1, 'visibility' => $visibility, 'status' => 'active', 'sort_order' => 0]);
    }

    public function test_localized_shop_requires_matching_zone_and_presents_only_visible_offers()
    {
        $this->seed(WorldCatalogSeeder::class);
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $character = Character::factory()->selectedFor($user = User::factory()->create())->create();
        $wallet = new CharacterWallet(['character_id' => $character->id]);
        $wallet->gold_balance = 100;
        $wallet->save();
        $shop = Shop::factory()->create(['name' => 'Tienda pública']);
        ShopLocation::create(['shop_id' => $shop->id, 'locatable_type' => ShopLocationType::ZONE, 'locatable_id' => $zone->id, 'status' => 'active', 'sort_order' => 0]);
        $offer = $this->offer($shop);
        $this->offer($shop, ShopOfferVisibility::HIDDEN);

        $response = $this->actingAs($user)->get(route('characters.shops.show', [$character, $shop, 'zone' => $zone->id]));
        $response->assertOk()->assertSee('Tienda pública')->assertSee('Poción del bosque')->assertSee('Precio del paquete')->assertSee('data-shop-catalog', false)->assertSee(route('characters.shops.offers.purchases.store', [$character, $shop, $offer]), false)->assertDontSee('Objeto oculto');
        $this->get(route('characters.shops.show', [$character, $shop]))->assertNotFound();
    }

    public function test_global_shop_opens_without_zone_and_foreign_character_is_forbidden()
    {
        $shop = Shop::factory()->create();
        $character = Character::factory()->selectedFor($user = User::factory()->create())->create();
        $this->offer($shop);
        $this->actingAs($user)->get(route('characters.shops.show', [$character, $shop]))->assertOk();
        $foreign = Character::factory()->selectedFor(User::factory()->create())->create();
        $this->actingAs($user)->get(route('characters.shops.show', [$foreign, $shop]))->assertForbidden();
    }

    public function test_markup_uses_structured_module_without_dynamic_inner_html()
    {
        $source = file_get_contents(base_path('src/assets/js/shop-purchase.js'));
        $main = file_get_contents(base_path('src/assets/js/main.js'));
        $this->assertStringContainsString("import './shop-purchase.js';", $main);
        $this->assertStringContainsString("document.querySelector('[data-shop-catalog]')", $source);
        $this->assertStringContainsString("querySelectorAll('[data-shop-buy]')", $source);
        $this->assertStringContainsString("querySelector('#shop-purchase-modal')", $source);
        $this->assertStringContainsString('textContent', $source);
        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringContainsString('idempotency_key', $source);
        $this->assertStringContainsString('keys.delete(offerId)', $source);
    }

    public function test_public_bundle_contains_shop_module_and_matches_latest_generated_asset()
    {
        $public = public_path('assets/js/main.js');
        $generated = base_path('dist/assets/js/main.js');
        $this->assertFileExists($public);
        $contents = file_get_contents($public);
        $this->assertStringContainsString('data-shop-catalog', $contents);
        $this->assertStringContainsString('No se pudo confirmar la respuesta', $contents);
        if (file_exists($generated)) {
            $this->assertSame(file_get_contents($generated), $contents);
            $this->assertGreaterThanOrEqual(filemtime($generated), filemtime($public));
        }
    }

    public function test_purchasable_offer_has_complete_enabled_button_and_modal_contract()
    {
        $shop = Shop::factory()->create();
        $character = Character::factory()->selectedFor($user = User::factory()->create())->create();
        $wallet = new CharacterWallet(['character_id' => $character->id]);
        $wallet->gold_balance = 100;
        $wallet->save();
        $this->offer($shop);
        $view = app(ShopReadService::class)->shop($user, $character, $shop)->toArray();
        $offer = $view['offers'][0];
        $this->assertTrue($offer['purchasable'], json_encode($offer));

        $response = $this->actingAs($user)->get(route('characters.shops.show', [$character, $shop]));
        $response->assertOk()->assertSee('data-shop-catalog', false)->assertSee('id="shop-purchase-modal"', false);
        preg_match('/<button[^>]*data-shop-buy[^>]*>/', $response->getContent(), $button);
        $this->assertNotEmpty($button);
        $this->assertStringContainsString('type="button"', $button[0]);
        foreach (['data-purchase-url=', 'data-item-name=', 'data-quantity=', 'data-price='] as $attribute) {
            $this->assertStringContainsString($attribute, $button[0]);
        }
        $this->assertStringNotContainsString(' disabled', $button[0]);
    }
}
