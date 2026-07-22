<?php

namespace Tests\Feature;

use App\Domain\Inventory\Instances\ItemInstanceEventType;
use App\Domain\Inventory\Instances\ItemInstanceOriginType;
use App\Domain\Shops\ShopOfferVisibility;
use App\Domain\Shops\ShopPurchaseService;
use App\Domain\Wallet\GoldReasonCode;
use App\Domain\Wallet\WalletService;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterWallet;
use App\Models\GoldTransaction;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Npc;
use App\Models\Shop;
use App\Models\ShopOffer;
use App\Models\ShopPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ShopPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_stackable_purchase_is_atomic_and_replay_does_not_repeat_effects()
    {
        list($user, $character) = $this->characterWithGold(1000);
        $item = $this->stackableItem();
        $offer = $this->offer($item, ['quantity' => 5, 'gold_price' => 250, 'stock_limit' => 10, 'stock_remaining' => 10]);
        $key = (string) Str::uuid();

        $response = $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => $key]);
        $response->assertStatus(201)->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.gold_spent', 250)
            ->assertJsonPath('data.previous_gold_balance', 1000)
            ->assertJsonPath('data.current_gold_balance', 750)
            ->assertJsonPath('data.stock_remaining', 9)
            ->assertJsonPath('data.replayed', false);

        $purchase = ShopPurchase::firstOrFail();
        $this->assertSame(5, CharacterItem::firstOrFail()->quantity);
        $this->assertSame(750, CharacterWallet::where('character_id', $character->id)->firstOrFail()->gold_balance);
        $this->assertSame(9, $offer->fresh()->stock_remaining);
        $this->assertDatabaseHas('gold_transactions', [
            'reason_code' => GoldReasonCode::SHOP_PURCHASE,
            'reference_type' => 'shop_purchase',
            'reference_id' => $purchase->id,
            'idempotency_key' => $key,
        ]);

        $this->postJson($this->url($character, $offer), ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.gold_spent', 250);
        $this->assertSame(1, ShopPurchase::count());
        $this->assertSame(1, GoldTransaction::count());
        $this->assertSame(5, CharacterItem::firstOrFail()->quantity);
        $this->assertSame(9, $offer->fresh()->stock_remaining);
    }

    public function test_unique_purchase_creates_one_instance_and_birth_event_per_unit()
    {
        list($user, $character) = $this->characterWithGold(500);
        $item = $this->uniqueEquipment();
        $offer = $this->offer($item, ['category' => 'weapons', 'quantity' => 2, 'gold_price' => 100]);
        $key = (string) Str::uuid();

        $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => $key])->assertStatus(201);

        $purchase = ShopPurchase::firstOrFail();
        $instances = ItemInstance::orderBy('origin_unit_index')->get();
        $this->assertCount(2, $instances);
        $this->assertSame([1, 2], $instances->pluck('origin_unit_index')->all());
        foreach ($instances as $instance) {
            $this->assertSame(ItemInstanceOriginType::SHOP_PURCHASE, $instance->origin_type);
            $this->assertSame($purchase->id, $instance->origin_id);
            $this->assertSame(0, $instance->refinement_level);
            $this->assertSame('available', $instance->status);
            $this->assertDatabaseHas('item_instance_events', [
                'item_instance_id' => $instance->id,
                'event_type' => ItemInstanceEventType::CREATED_FROM_SHOP_PURCHASE,
                'source_type' => ItemInstanceOriginType::SHOP_PURCHASE,
                'source_id' => $purchase->id,
            ]);
        }

        $uuids = $instances->pluck('uuid')->all();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => $key])->assertOk();
        $this->assertSame($uuids, ItemInstance::orderBy('origin_unit_index')->pluck('uuid')->all());
    }

    public function test_capacity_and_insufficient_gold_roll_back_every_effect()
    {
        list($user, $character) = $this->characterWithGold(50);
        $character->base_inventory_slots = 1;
        $character->save();
        $item = $this->uniqueEquipment();
        $offer = $this->offer($item, ['category' => 'weapons', 'quantity' => 2, 'gold_price' => 100]);

        $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422);
        $this->assertSame(0, ShopPurchase::count());
        $this->assertSame(0, GoldTransaction::count());
        $this->assertSame(0, ItemInstance::count());
        $this->assertSame(50, CharacterWallet::firstOrFail()->gold_balance);

        $character->base_inventory_slots = 30;
        $character->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonPath('message', 'Oro insuficiente.');
        $this->assertSame(0, ShopPurchase::count());
        $this->assertSame(0, ItemInstance::count());
    }

    public function test_stock_limit_and_per_character_limit_are_authoritative()
    {
        list($user, $character) = $this->characterWithGold(500);
        $item = $this->stackableItem();
        $offer = $this->offer($item, ['stock_limit' => 1, 'stock_remaining' => 1, 'purchase_limit_per_character' => 1]);

        $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(201);
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $this->assertSame(0, $offer->fresh()->stock_remaining);
        $this->assertSame(1, ShopPurchase::count());
    }

    public function test_hidden_shop_hidden_offer_windows_inactive_item_and_level_are_rejected()
    {
        list($user, $character) = $this->characterWithGold(500);
        $item = $this->stackableItem();
        $offer = $this->offer($item);
        $this->actingAs($user);

        $offer->shop->status = 'hidden';
        $offer->shop->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $offer->shop->status = 'active';
        $offer->shop->save();

        $offer->visibility = ShopOfferVisibility::HIDDEN;
        $offer->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $offer->visibility = ShopOfferVisibility::VISIBLE;
        $offer->required_character_level = 2;
        $offer->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $offer->required_character_level = null;
        $offer->save();
        $item->status = 'inactive';
        $item->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid()])->assertStatus(422);

        $this->assertSame(0, ShopPurchase::count());
    }

    public function test_foreign_character_offer_mismatch_extra_price_and_idempotency_conflict_are_rejected()
    {
        list($user, $character) = $this->characterWithGold(500);
        $offer = $this->offer($this->stackableItem());
        $foreign = Character::factory()->create();
        $this->actingAs($user)->postJson($this->url($foreign, $offer), ['idempotency_key' => (string) Str::uuid()])->assertForbidden();

        $otherOffer = $this->offer($this->stackableItem('other-material'), [], Shop::factory()->create());
        $this->postJson(route('characters.shops.offers.purchases.store', [$character, $offer->shop, $otherOffer]), ['idempotency_key' => (string) Str::uuid()])->assertNotFound();

        $this->postJson($this->url($character, $offer), ['idempotency_key' => (string) Str::uuid(), 'gold_price' => 1, 'quantity' => 999])->assertStatus(422);

        $key = (string) Str::uuid();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => $key])->assertStatus(201);
        $this->postJson($this->url($character, $otherOffer), ['idempotency_key' => $key])->assertStatus(409);
    }

    public function test_replay_uses_snapshot_after_catalog_changes_and_tolerates_optional_metadata_missing()
    {
        list($user, $character) = $this->characterWithGold(500);
        $item = $this->stackableItem();
        $offer = $this->offer($item, ['gold_price' => 100]);
        $key = (string) Str::uuid();
        $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => $key])->assertStatus(201);

        $purchase = ShopPurchase::firstOrFail();
        $offer->gold_price = 999;
        $offer->status = 'inactive';
        $offer->save();
        $item->name = 'Nombre nuevo';
        $item->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.gold_spent', 100)
            ->assertJsonPath('data.item_name', 'Material de prueba');

        $purchase->metadata = ['snapshot_version' => 1];
        $purchase->save();
        $this->postJson($this->url($character, $offer), ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('data.item_name', null);
        $this->assertSame(1, ShopPurchase::count());
        $this->assertSame(1, GoldTransaction::count());
    }

    public function test_global_wallet_key_collision_rolls_back_purchase_and_delivery()
    {
        list($user, $character) = $this->characterWithGold(500);
        $offer = $this->offer($this->stackableItem());
        $key = (string) Str::uuid();
        app(WalletService::class)->credit($character, 10, GoldReasonCode::CORRECTION, 'Existing operation', null, null, $key);
        $balance = $character->wallet->fresh()->gold_balance;

        $this->actingAs($user)->postJson($this->url($character, $offer), ['idempotency_key' => $key])
            ->assertStatus(409);

        $this->assertSame(0, ShopPurchase::count());
        $this->assertSame(0, CharacterItem::count());
        $this->assertSame($balance, $character->wallet->fresh()->gold_balance);
        $this->assertSame(1, GoldTransaction::count());
    }

    private function characterWithGold($gold)
    {
        $user = User::factory()->create();
        $character = Character::factory()->selectedFor($user)->create();
        $wallet = new CharacterWallet(['character_id' => $character->id]);
        $wallet->gold_balance = $gold;
        $wallet->save();
        return [$user, $character];
    }

    private function stackableItem($code = 'shop-material')
    {
        return Item::create(['code' => $code, 'name' => 'Material de prueba', 'description' => null, 'item_type' => 'material', 'equipment_type' => null, 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => 99, 'status' => 'active']);
    }

    private function uniqueEquipment()
    {
        return Item::create(['code' => 'shop-sword', 'name' => 'Espada de tienda', 'description' => null, 'item_type' => 'equipment', 'equipment_type' => 'weapon', 'hand_requirement' => 'one_hand', 'equipment_family' => 'sword', 'required_level' => 5, 'rarity' => 'common', 'is_stackable' => false, 'max_stack' => 1, 'status' => 'active', 'max_health_bonus' => 0, 'attack_bonus' => 1, 'defense_bonus' => 0, 'accuracy_bonus' => 0, 'evasion_bonus' => 0, 'critical_chance_bonus' => 0, 'attack_speed_bonus' => 0]);
    }

    private function offer(Item $item, array $overrides = [], Shop $shop = null)
    {
        $shop = $shop ?: Shop::factory()->for(Npc::factory())->create();
        return ShopOffer::create(array_merge(['shop_id' => $shop->id, 'item_id' => $item->id, 'category' => 'materials', 'quantity' => 1, 'gold_price' => 25, 'stock_limit' => null, 'stock_remaining' => null, 'purchase_limit_per_character' => null, 'required_character_level' => null, 'visibility' => 'visible', 'status' => 'active', 'starts_at' => null, 'ends_at' => null, 'sort_order' => 0], $overrides));
    }

    private function url(Character $character, ShopOffer $offer)
    {
        return route('characters.shops.offers.purchases.store', [$character, $offer->shop, $offer]);
    }
}
