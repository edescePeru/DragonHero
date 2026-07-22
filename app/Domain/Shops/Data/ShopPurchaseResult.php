<?php

namespace App\Domain\Shops\Data;

final class ShopPurchaseResult
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = array_merge([], $data);
    }

    public function toArray()
    {
        return array_merge([], $this->data);
    }

    public function replayed()
    {
        return $this->data['replayed'];
    }
}
