<?php

namespace Tests\Feature;

use App\Domain\Shops\Exceptions\ShopPurchaseIdempotencyConflictException;
use App\Domain\Shops\Exceptions\ShopStockUnavailableException;
use App\Domain\Shops\ShopPurchaseService;
use App\Models\Character;
use App\Models\CharacterWallet;
use App\Models\Item;
use App\Models\Npc;
use App\Models\Shop;
use App\Models\ShopOffer;
use App\Models\ShopPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ShopPurchaseConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_characters_contending_for_last_package_produce_one_purchase()
    {
        list($firstUser, $first) = $this->characterWithGold();
        list($secondUser, $second) = $this->characterWithGold();
        $offer = $this->limitedOffer();
        $service = app(ShopPurchaseService::class);

        $service->purchase($firstUser, $first, $offer->shop, $offer, (string) Str::uuid());

        try {
            $service->purchase($secondUser, $second, $offer->shop, $offer, (string) Str::uuid());
            $this->fail('The exhausted package was purchased twice.');
        } catch (ShopStockUnavailableException $exception) {
            $this->assertSame(1, ShopPurchase::count());
            $this->assertSame(0, $offer->fresh()->stock_remaining);
            $this->assertSame(100, $second->wallet->fresh()->gold_balance);
        }
    }

    public function test_same_key_replays_same_context_and_conflicts_globally_with_another_character()
    {
        list($firstUser, $first) = $this->characterWithGold();
        list($secondUser, $second) = $this->characterWithGold();
        $offer = $this->limitedOffer(2);
        $service = app(ShopPurchaseService::class);
        $key = (string) Str::uuid();

        $created = $service->purchase($firstUser, $first, $offer->shop, $offer, $key);
        $replayed = $service->purchase($firstUser, $first, $offer->shop, $offer, $key);
        $this->assertFalse($created->replayed());
        $this->assertTrue($replayed->replayed());

        $this->expectException(ShopPurchaseIdempotencyConflictException::class);
        try {
            $service->purchase($secondUser, $second, $offer->shop, $offer, $key);
        } finally {
            $this->assertSame(1, ShopPurchase::count());
            $this->assertSame(1, $offer->fresh()->stock_remaining);
            $this->assertSame(100, $second->wallet->fresh()->gold_balance);
        }
    }

    private function characterWithGold()
    {
        $user = User::factory()->create();
        $character = Character::factory()->for($user)->create();
        $wallet = new CharacterWallet(['character_id' => $character->id]);
        $wallet->gold_balance = 100;
        $wallet->save();
        return [$user, $character];
    }

    private function limitedOffer($stock = 1)
    {
        $item = Item::create(['code' => 'concurrent-material', 'name' => 'Concurrent material', 'description' => null, 'item_type' => 'material', 'equipment_type' => null, 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => 99, 'status' => 'active']);
        $shop = Shop::factory()->for(Npc::factory())->create();
        return ShopOffer::create(['shop_id' => $shop->id, 'item_id' => $item->id, 'category' => 'materials', 'quantity' => 1, 'gold_price' => 25, 'stock_limit' => $stock, 'stock_remaining' => $stock, 'purchase_limit_per_character' => null, 'required_character_level' => null, 'visibility' => 'visible', 'status' => 'active', 'starts_at' => null, 'ends_at' => null, 'sort_order' => 0]);
    }
}
