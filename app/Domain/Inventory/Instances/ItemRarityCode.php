<?php

namespace App\Domain\Inventory\Instances;

use InvalidArgumentException;

final class ItemRarityCode
{
    const COMMON = 'common';
    const RARE = 'rare';
    const MYTHIC = 'mythic';
    const LEGENDARY = 'legendary';

    public static function values()
    {
        return [self::COMMON, self::RARE, self::MYTHIC, self::LEGENDARY];
    }

    public static function styles()
    {
        return ['neutral', 'blue', 'purple', 'gold'];
    }

    public static function assert($code)
    {
        if (!is_string($code) || !in_array($code, self::values(), true)) {
            throw new InvalidArgumentException('Invalid official Item rarity code.');
        }
        return $code;
    }

    private function __construct()
    {
    }
}
