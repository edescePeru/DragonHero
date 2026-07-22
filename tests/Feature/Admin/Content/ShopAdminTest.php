<?php

namespace Tests\Feature\Admin\Content;

use App\Domain\Media\MediaAssetType;
use App\Domain\Shops\ShopLocationType;
use App\Models\Item;
use App\Models\Npc;
use App\Models\Shop;
use App\Models\ShopOffer;
use App\Models\User;
use App\Models\World;
use App\Models\Region;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ShopAdminTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['game_admin.emails' => ['shop-admin@example.test']]);
        $this->admin = User::factory()->create(['email' => 'shop-admin@example.test']);
    }

    public function test_access_is_restricted_and_npc_can_be_managed_without_delete_routes()
    {
        $this->get(route('admin.content.npcs.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->get(route('admin.content.npcs.index'))->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.content.npcs.store'), [
            'code' => 'Village Merchant',
            'name' => 'Mercader del pueblo',
            'greeting' => 'Bienvenido.',
            'status' => 'active',
        ])->assertRedirect();

        $npc = Npc::where('code', 'village-merchant')->firstOrFail();
        $this->get(route('admin.content.npcs.edit', $npc))->assertOk()
            ->assertSee('Mercader del pueblo');
        $this->patch(route('admin.content.npcs.deactivate', $npc))->assertRedirect();
        $this->assertSame('inactive', $npc->fresh()->status);
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())
            ->contains(function ($route) {
                return in_array('DELETE', $route->methods(), true)
                    && strpos((string) $route->getName(), 'admin.content.npcs') === 0;
            }));
    }

    public function test_shop_can_be_created_with_zone_locations_and_status_changes_preserve_them()
    {
        $npc = Npc::factory()->create();
        $zone = $this->zone();

        $response = $this->actingAs($this->admin)->post(route('admin.content.shops.store'), [
            'code' => 'Valtheria Armory',
            'name' => 'Armería de Valtheria',
            'description' => 'Equipo inicial.',
            'npc_id' => $npc->id,
            'status' => 'active',
            'starts_at' => '2026-07-20T10:00',
            'ends_at' => '2026-08-20T10:00',
            'sort_order' => 2,
            'zone_ids' => [$zone->id],
        ]);

        $response->assertRedirect();
        $shop = Shop::where('code', 'valtheria-armory')->firstOrFail();
        $this->assertDatabaseHas('shop_locations', [
            'shop_id' => $shop->id,
            'locatable_type' => ShopLocationType::ZONE,
            'locatable_id' => $zone->id,
        ]);

        $this->patch(route('admin.content.shops.hide', $shop))->assertRedirect();
        $this->assertSame('hidden', $shop->fresh()->status);
        $this->assertSame(1, $shop->locations()->count());
        $this->assertNotNull($shop->fresh()->starts_at);
    }

    public function test_offer_crud_validates_sellable_item_category_stock_and_search()
    {
        $shop = Shop::factory()->create();
        $material = $this->material('wolf-hide', 'Cuero de lobo');

        $this->actingAs($this->admin)->post(
            route('admin.content.shops.offers.store', $shop),
            $this->offerPayload($material)
        )->assertRedirect();

        $offer = ShopOffer::firstOrFail();
        $this->assertSame(20, $offer->stock_limit);
        $this->assertSame(20, $offer->stock_remaining);

        $this->get(route('admin.content.shops.items.search', ['q' => 'Cuero']))
            ->assertOk()->assertJsonPath('data.0.id', $material->id);

        $this->put(
            route('admin.content.shops.offers.update', [$shop, $offer]),
            $this->offerPayload($material, ['category' => 'weapons'])
        )->assertSessionHasErrors('offer');

        $this->patch(route('admin.content.shops.offers.deactivate', [$shop, $offer]))
            ->assertRedirect();
        $this->assertSame('inactive', $offer->fresh()->status);
        $this->patch(route('admin.content.shops.offers.activate', [$shop, $offer]))
            ->assertRedirect();
        $this->assertSame('active', $offer->fresh()->status);
    }

    public function test_npc_and_shop_media_are_independent_and_replace_safely()
    {
        $this->actingAs($this->admin)->post(route('admin.content.npcs.store'), [
            'code' => 'portrait-merchant',
            'name' => 'Retrato',
            'greeting' => null,
            'status' => 'active',
            'npc_portrait' => UploadedFile::fake()->image('merchant.png', 300, 400),
        ])->assertRedirect();

        $npc = Npc::where('code', 'portrait-merchant')->firstOrFail();
        $portrait = $npc->primaryMediaAsset(MediaAssetType::PORTRAIT)->firstOrFail();
        Storage::disk('public')->assertExists($portrait->path);

        $shop = Shop::factory()->for($npc)->create();
        $payload = [
            'code' => $shop->code,
            'name' => $shop->name,
            'description' => null,
            'npc_id' => $npc->id,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 0,
            'shop_banner' => UploadedFile::fake()->image('banner.jpg', 800, 300),
            'shop_background' => UploadedFile::fake()->image('background.png', 1280, 720),
        ];
        $this->put(route('admin.content.shops.update', $shop), $payload)->assertRedirect();

        $this->assertTrue($shop->primaryMediaAsset(MediaAssetType::BANNER)->exists());
        $this->assertTrue($shop->primaryMediaAsset(MediaAssetType::BACKGROUND)->exists());

        unset($payload['shop_banner'], $payload['shop_background']);
        $payload['remove_shop_banner'] = 1;
        $this->put(route('admin.content.shops.update', $shop), $payload)->assertRedirect();
        $this->assertFalse($shop->primaryMediaAsset(MediaAssetType::BANNER)->exists());
        $this->assertTrue($shop->primaryMediaAsset(MediaAssetType::BACKGROUND)->exists());
        $this->assertTrue($npc->primaryMediaAsset(MediaAssetType::PORTRAIT)->exists());
    }

    private function offerPayload(Item $item, array $overrides = [])
    {
        return array_merge([
            'item_id' => $item->id,
            'category' => 'materials',
            'quantity' => '2',
            'gold_price' => '15',
            'visibility' => 'visible',
            'status' => 'active',
            'stock_mode' => 'limited',
            'stock_limit' => '20',
            'stock_remaining' => '20',
            'purchase_limit_per_character' => null,
            'required_character_level' => null,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 0,
        ], $overrides);
    }

    private function material($code, $name)
    {
        return Item::create([
            'code' => $code,
            'name' => $name,
            'description' => null,
            'item_type' => 'material',
            'equipment_type' => null,
            'rarity' => 'common',
            'is_stackable' => true,
            'max_stack' => 99,
            'status' => 'active',
        ]);
    }

    private function zone()
    {
        $world = World::create(['code' => 'shop-world', 'name' => 'Shop World', 'description' => null, 'status' => 'active', 'sort_order' => 0]);
        $region = Region::create(['world_id' => $world->id, 'code' => 'shop-region', 'name' => 'Shop Region', 'description' => null, 'status' => 'active', 'sort_order' => 0]);

        return Zone::create(['region_id' => $region->id, 'code' => 'shop-zone', 'name' => 'Shop Zone', 'description' => null, 'zone_type' => 'wilderness', 'recommended_level_min' => 1, 'recommended_level_max' => 5, 'is_safe' => false, 'allows_hunting' => true, 'status' => 'active', 'sort_order' => 0]);
    }
}
