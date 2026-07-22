<?php

namespace App\Http\Controllers;

use App\Domain\Shops\Exceptions\ShopInventoryCapacityException;
use App\Domain\Shops\Exceptions\ShopOfferMismatchException;
use App\Domain\Shops\Exceptions\ShopOfferUnavailableException;
use App\Domain\Shops\Exceptions\ShopPurchaseIdempotencyConflictException;
use App\Domain\Shops\Exceptions\ShopPurchaseLimitReachedException;
use App\Domain\Shops\Exceptions\ShopStockUnavailableException;
use App\Domain\Shops\Exceptions\ShopUnavailableException;
use App\Domain\Shops\ShopPurchaseService;
use App\Domain\Wallet\Exceptions\InsufficientGoldException;
use App\Http\Requests\PurchaseShopOfferRequest;
use App\Models\Character;
use App\Models\Shop;
use App\Models\ShopOffer;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class ShopPurchaseController extends Controller
{
    public function store(PurchaseShopOfferRequest $request, Character $character, Shop $shop, ShopOffer $offer, ShopPurchaseService $service)
    {
        try {
            $result = $service->purchase($request->user(), $character, $shop, $offer, $request->validated()['idempotency_key']);

            return response()->json([
                'success' => true,
                'message' => $result->replayed() ? 'Compra recuperada correctamente.' : 'Compra realizada correctamente.',
                'data' => $result->toArray(),
            ], $result->replayed() ? 200 : 201);
        } catch (AuthorizationException $exception) {
            return $this->failure('No puedes comprar con este personaje.', 403);
        } catch (ShopOfferMismatchException $exception) {
            return $this->failure($exception->getMessage(), 404);
        } catch (ShopPurchaseIdempotencyConflictException $exception) {
            return $this->failure($exception->getMessage(), 409);
        } catch (ShopStockUnavailableException $exception) {
            return $this->failure($exception->getMessage(), 409);
        } catch (ShopPurchaseLimitReachedException $exception) {
            return $this->failure($exception->getMessage(), 409);
        } catch (ShopUnavailableException $exception) {
            return $this->failure($exception->getMessage(), 409);
        } catch (ShopOfferUnavailableException $exception) {
            return $this->failure($exception->getMessage(), 409);
        } catch (ShopInventoryCapacityException $exception) {
            return $this->failure($exception->getMessage(), 422);
        } catch (InsufficientGoldException $exception) {
            return $this->failure('Oro insuficiente.', 422);
        } catch (InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage(), 422);
        }
    }

    private function failure($message, $status)
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }
}
