<?php

namespace App\Domain\Inventory\Instances;

use App\Domain\Equipment\EquipmentItemFamily;
use App\Domain\Equipment\EquipmentType;
use App\Models\Item;
use InvalidArgumentException;

final class ItemRefinementConfiguration
{
    const NONE = 'none';
    const ATTACK = 'attack';
    const DEFENSE = 'defense';

    public static function values()
    {
        return [self::NONE, self::ATTACK, self::DEFENSE];
    }

    public function validate(Item $item)
    {
        $allows = (bool) $item->allows_refinement;
        $stat = $item->refinement_stat;
        if (!in_array($stat, self::values(), true)) {
            throw new InvalidArgumentException('Invalid refinement stat.');
        }
        if (!$allows && $stat !== self::NONE) {
            throw new InvalidArgumentException('A non-refinable Item must use refinement stat none.');
        }
        if ($allows && $stat === self::NONE) {
            throw new InvalidArgumentException('A refinable Item requires a primary refinement stat.');
        }
        if (!$allows) {
            return;
        }
        if ($item->item_type !== 'equipment') {
            throw new InvalidArgumentException('Only equipment can allow refinement.');
        }
        if ($stat === self::ATTACK && !$this->isWeapon($item)) {
            throw new InvalidArgumentException('Only a weapon can refine attack.');
        }
        if ($stat === self::DEFENSE && !$this->isDefensive($item)) {
            throw new InvalidArgumentException('Only armor or a shield can refine defense.');
        }
    }

    public function isWeapon(Item $item)
    {
        return $item->equipment_type === EquipmentType::WEAPON
            && ($item->equipment_family === null || EquipmentItemFamily::isWeapon($item->equipment_family));
    }

    public function isDefensive(Item $item)
    {
        return in_array($item->equipment_type, [EquipmentType::HELMET, EquipmentType::ARMOR, EquipmentType::GLOVES, EquipmentType::BOOTS], true)
            || ($item->equipment_type === EquipmentType::WEAPON && $item->equipment_family === EquipmentItemFamily::SHIELD);
    }
}
