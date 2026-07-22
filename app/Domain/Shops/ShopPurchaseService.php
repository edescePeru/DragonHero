<?php

namespace App\Domain\Shops;

use App\Domain\Inventory\Capacity\InventoryCapacityProjectionService;
use App\Domain\Inventory\Instances\ItemInstanceService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\ItemClassification;
use App\Domain\Shops\Data\ShopPurchaseResult;
use App\Domain\Shops\Exceptions\ShopInventoryCapacityException;
use App\Domain\Shops\Exceptions\ShopOfferMismatchException;
use App\Domain\Shops\Exceptions\ShopOfferUnavailableException;
use App\Domain\Shops\Exceptions\ShopPurchaseIdempotencyConflictException;
use App\Domain\Shops\Exceptions\ShopPurchaseLimitReachedException;
use App\Domain\Shops\Exceptions\ShopStockUnavailableException;
use App\Domain\Shops\Exceptions\ShopUnavailableException;
use App\Domain\Wallet\GoldReasonCode;
use App\Domain\Wallet\WalletService;
use App\Domain\Wallet\Exceptions\IdempotencyConflictException as WalletIdempotencyConflictException;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterWallet;
use App\Models\InventoryCapacityGrant;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\Shop;
use App\Models\ShopOffer;
use App\Models\ShopPurchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ShopPurchaseService
{
    const SNAPSHOT_VERSION = 1;

    private $availability;
    private $validator;
    private $projection;
    private $classification;
    private $wallet;
    private $inventory;
    private $instances;

    public function __construct(
        ShopAvailabilityService $availability,
        ShopCatalogValidator $validator,
        InventoryCapacityProjectionService $projection,
        ItemClassification $classification,
        WalletService $wallet,
        InventoryService $inventory,
        ItemInstanceService $instances
    ) {
        $this->availability = $availability;
        $this->validator = $validator;
        $this->projection = $projection;
        $this->classification = $classification;
        $this->wallet = $wallet;
        $this->inventory = $inventory;
        $this->instances = $instances;
    }

    public function purchase(User $user, Character $character, Shop $shop, ShopOffer $offer, $idempotencyKey)
    {
        $this->assertUuid($idempotencyKey);

        try {
            return DB::transaction(function () use ($user, $character, $shop, $offer, $idempotencyKey) {
                $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedCharacter->user_id !== (int) $user->id) {
                    throw new AuthorizationException('Character does not belong to the authenticated user.');
                }

                $existing = ShopPurchase::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $this->replay($existing, $lockedCharacter, $shop, $offer);
                }

                $lockedShop = Shop::with('npc')->whereKey($shop->id)->lockForUpdate()->firstOrFail();
                $lockedOffer = ShopOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedOffer->shop_id !== (int) $lockedShop->id) {
                    throw new ShopOfferMismatchException('La oferta no pertenece a la tienda.');
                }

                $lockedItem = Item::whereKey($lockedOffer->item_id)->lockForUpdate()->firstOrFail();
                $lockedOffer->setRelation('shop', $lockedShop);
                $lockedOffer->setRelation('item', $lockedItem);
                $now = CarbonImmutable::now('UTC');

                $this->validator->validateShop($lockedShop);
                if (! $this->availability->isShopVisible($lockedShop, $now)) {
                    throw new ShopUnavailableException('La tienda no está disponible.');
                }
                $this->validator->validateOffer($lockedOffer);
                $this->validator->validateSellableItem($lockedItem, $lockedOffer->category);
                if ($lockedOffer->required_character_level !== null && (int) $lockedCharacter->level < (int) $lockedOffer->required_character_level) {
                    throw new InvalidArgumentException('El personaje no cumple el nivel requerido por la oferta.');
                }
                if ($lockedOffer->stock_remaining !== null && (int) $lockedOffer->stock_remaining < 1) {
                    throw new ShopStockUnavailableException('La oferta no tiene stock disponible.');
                }
                if (! $this->availability->isOfferPurchasable($lockedOffer, $now, $lockedCharacter)) {
                    throw new ShopOfferUnavailableException('La oferta no está disponible para la compra.');
                }

                $lockedWallet = CharacterWallet::where('character_id', $lockedCharacter->id)->lockForUpdate()->first();
                if (! $lockedWallet) {
                    $lockedWallet = new CharacterWallet();
                    $lockedWallet->character_id = $lockedCharacter->id;
                    $lockedWallet->gold_balance = 0;
                    $lockedWallet->save();
                }

                $inventory = CharacterItem::where('character_id', $lockedCharacter->id)->orderBy('id')->lockForUpdate()->get();
                $ownedInstances = ItemInstance::where('character_id', $lockedCharacter->id)->orderBy('id')->lockForUpdate()->get();
                InventoryCapacityGrant::where('character_id', $lockedCharacter->id)->orderBy('id')->lockForUpdate()->get();

                $purchaseCount = ShopPurchase::where('character_id', $lockedCharacter->id)
                    ->where('shop_offer_id', $lockedOffer->id)->count();
                if ($lockedOffer->purchase_limit_per_character !== null && $purchaseCount >= (int) $lockedOffer->purchase_limit_per_character) {
                    throw new ShopPurchaseLimitReachedException('El personaje alcanzó el límite de compras de esta oferta.');
                }

                $itemIds = $inventory->pluck('item_id')->merge($ownedInstances->pluck('item_id'))->push($lockedItem->id)->unique();
                $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
                $delivery = collect([(object) ['item_id' => $lockedItem->id, 'quantity' => (int) $lockedOffer->quantity]]);
                $capacity = $this->projection->fromLoadedState($lockedCharacter, $inventory, $delivery, $items, $now, $ownedInstances);
                if (! $capacity->claimFits()) {
                    throw new ShopInventoryCapacityException('No hay capacidad para recibir la compra completa.');
                }
                if ((int) $lockedWallet->gold_balance < (int) $lockedOffer->gold_price) {
                    throw new \App\Domain\Wallet\Exceptions\InsufficientGoldException('Oro insuficiente.');
                }

                $purchase = ShopPurchase::create([
                    'shop_id' => $lockedShop->id,
                    'shop_offer_id' => $lockedOffer->id,
                    'character_id' => $lockedCharacter->id,
                    'item_id' => $lockedItem->id,
                    'quantity_purchased' => (int) $lockedOffer->quantity,
                    'gold_spent' => (int) $lockedOffer->gold_price,
                    'idempotency_key' => $idempotencyKey,
                    'purchased_at' => $now,
                    'metadata' => null,
                ]);

                try {
                    $gold = $this->wallet->debitLocked(
                        $lockedCharacter,
                        (int) $lockedOffer->gold_price,
                        GoldReasonCode::SHOP_PURCHASE,
                        'Compra de oferta '.$lockedOffer->id.' en tienda '.$lockedShop->id,
                        'shop_purchase',
                        (int) $purchase->id,
                        $idempotencyKey,
                        $lockedWallet
                    );
                } catch (WalletIdempotencyConflictException $exception) {
                    throw new ShopPurchaseIdempotencyConflictException('La clave idempotente ya pertenece a otra operación económica.');
                }

                if ($this->classification->classify($lockedItem) === ItemClassification::STACKABLE) {
                    $this->inventory->addManyLocked($lockedCharacter, $items, [(int) $lockedItem->id => (int) $lockedOffer->quantity], $inventory);
                } else {
                    $this->instances->createFromShopPurchaseLocked($lockedCharacter, $lockedItem, $purchase, (int) $lockedOffer->quantity, $now);
                }

                if ($lockedOffer->stock_remaining !== null) {
                    $lockedOffer->stock_remaining = (int) $lockedOffer->stock_remaining - 1;
                    $lockedOffer->save();
                }

                $updatedInventory = CharacterItem::where('character_id', $lockedCharacter->id)->orderBy('id')->get();
                $updatedInstances = ItemInstance::where('character_id', $lockedCharacter->id)->orderBy('id')->get();
                $updatedItems = Item::whereIn('id', $updatedInventory->pluck('item_id')->merge($updatedInstances->pluck('item_id'))->unique())->get()->keyBy('id');
                $afterCapacity = $this->projection->fromLoadedState($lockedCharacter, $updatedInventory, collect(), $updatedItems, $now, $updatedInstances);
                $purchaseCount++;
                $capacityData = $afterCapacity->toArray();
                $purchase->metadata = [
                    'snapshot_version' => self::SNAPSHOT_VERSION,
                    'item_code' => $lockedItem->code,
                    'item_name' => $lockedItem->name,
                    'previous_gold_balance' => $gold->toArray()['balance_before'],
                    'current_gold_balance' => $gold->toArray()['balance_after'],
                    'stock_remaining_after' => $lockedOffer->stock_remaining === null ? null : (int) $lockedOffer->stock_remaining,
                    'purchase_count_after' => $purchaseCount,
                    'inventory_slots_used_after' => $capacityData['current_used_slots'],
                    'inventory_slots_capacity_after' => $capacityData['effective_capacity'],
                ];
                $purchase->save();

                return $this->result($purchase, false);
            }, 3);
        } catch (QueryException $exception) {
            $existing = ShopPurchase::where('idempotency_key', $idempotencyKey)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $character, $shop, $offer);
        }
    }

    private function replay(ShopPurchase $purchase, Character $character, Shop $shop, ShopOffer $offer)
    {
        if ((int) $purchase->character_id !== (int) $character->id
            || (int) $purchase->shop_id !== (int) $shop->id
            || (int) $purchase->shop_offer_id !== (int) $offer->id) {
            throw new ShopPurchaseIdempotencyConflictException('La clave idempotente pertenece a otra compra.');
        }

        return $this->result($purchase, true);
    }

    private function result(ShopPurchase $purchase, $replayed)
    {
        $snapshot = is_array($purchase->metadata) ? $purchase->metadata : [];

        return new ShopPurchaseResult([
            'purchase_id' => (int) $purchase->id,
            'shop_id' => (int) $purchase->shop_id,
            'offer_id' => (int) $purchase->shop_offer_id,
            'item_id' => (int) $purchase->item_id,
            'item_code' => isset($snapshot['item_code']) ? (string) $snapshot['item_code'] : null,
            'item_name' => isset($snapshot['item_name']) ? (string) $snapshot['item_name'] : null,
            'quantity' => (int) $purchase->quantity_purchased,
            'gold_spent' => (int) $purchase->gold_spent,
            'previous_gold_balance' => $this->nullableInteger($snapshot, 'previous_gold_balance'),
            'current_gold_balance' => $this->nullableInteger($snapshot, 'current_gold_balance'),
            'stock_remaining' => $this->nullableInteger($snapshot, 'stock_remaining_after'),
            'purchase_count_for_character' => $this->nullableInteger($snapshot, 'purchase_count_after'),
            'inventory_slots_used' => $this->nullableInteger($snapshot, 'inventory_slots_used_after'),
            'inventory_slots_capacity' => $this->nullableInteger($snapshot, 'inventory_slots_capacity_after'),
            'replayed' => (bool) $replayed,
            'purchased_at' => $purchase->purchased_at->toIso8601String(),
        ]);
    }

    private function nullableInteger(array $snapshot, $key)
    {
        return isset($snapshot[$key]) && is_numeric($snapshot[$key]) ? (int) $snapshot[$key] : null;
    }

    private function assertUuid($value)
    {
        if (! is_string($value) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new InvalidArgumentException('La clave idempotente debe ser un UUID válido.');
        }
    }
}
