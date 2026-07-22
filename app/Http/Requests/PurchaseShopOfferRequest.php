<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseShopOfferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'gold_price' => ['prohibited'],
            'quantity' => ['prohibited'],
            'item_id' => ['prohibited'],
            'stock' => ['prohibited'],
            'discount' => ['prohibited'],
            'wallet_balance' => ['prohibited'],
            'purchase_limit' => ['prohibited'],
            'category' => ['prohibited'],
            'visibility' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
