<?php

namespace App\Domain\Characters;

final class CharacterStatus
{
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const BLOCKED = 'blocked';

    private function __construct()
    {
    }

    public static function values()
    {
        return [self::ACTIVE, self::INACTIVE, self::BLOCKED];
    }
}
