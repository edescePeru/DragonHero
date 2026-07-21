<?php

namespace App\Domain\Inventory;

use InvalidArgumentException;

final class InventoryStackExpander
{
    public function expand($quantity, $maxStack)
    {
        if (!is_int($quantity) || !is_int($maxStack) || $quantity <= 0 || $maxStack <= 1) {
            throw new InvalidArgumentException('Invalid stack expansion input.');
        }

        $stacks = [];
        $remaining = $quantity;
        while ($remaining > 0) {
            $stack = min($remaining, $maxStack);
            $stacks[] = $stack;
            $remaining -= $stack;
        }

        return $stacks;
    }
}
