<?php

namespace App\Domain\Inventory\Instances;

use App\Domain\Equipment\Data\ItemStatBonuses;
use App\Domain\Inventory\Instances\Data\EffectiveItemStats;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\RefinementStatModifier;
use InvalidArgumentException;

final class EffectiveItemStatsResolver
{
    private $configuration;
    private $scaling;

    public function __construct(ItemRefinementConfiguration $configuration, RefinementStatScaling $scaling)
    {
        $this->configuration = $configuration;
        $this->scaling = $scaling;
    }

    public function resolve(Item $item, ItemInstance $instance, RefinementStatModifier $modifier = null)
    {
        if ((int) $instance->item_id !== (int) $item->id) {
            throw new InvalidArgumentException('ItemInstance and Item mismatch.');
        }
        $rarity = $instance->relationLoaded('itemRarity') ? $instance->itemRarity : $instance->itemRarity()->first();
        if (!$rarity) {
            throw new InvalidArgumentException('ItemInstance rarity is missing.');
        }
        $this->configuration->validate($item);
        $base = $this->base($item);
        $points = $modifier ? (int) $modifier->stat_increase_basis_points : $this->scaling->basisPoints((int) $instance->refinement_level);
        $attack = 0;
        $defense = 0;
        if ($item->allows_refinement && $item->refinement_stat === ItemRefinementConfiguration::ATTACK) {
            $attack = $this->increase((int) $item->attack_bonus, $points);
        } elseif ($item->allows_refinement && $item->refinement_stat === ItemRefinementConfiguration::DEFENSE) {
            $defense = $this->increase((int) $item->defense_bonus, $points);
        }
        $refinement = new ItemStatBonuses(0, $attack, $defense, 0, 0, '0.00', '0.00', 0);
        $rarityBonuses = $this->rarity($item, $rarity);
        return new EffectiveItemStats($base, $refinement, $rarityBonuses, $this->add($this->add($base, $refinement), $rarityBonuses), $points);
    }

    private function base(Item $item)
    {
        return new ItemStatBonuses((int) $item->max_health_bonus, (int) $item->attack_bonus, (int) $item->defense_bonus, (int) $item->accuracy_bonus, (int) $item->evasion_bonus, $item->critical_chance_bonus, $item->attack_speed_bonus, (int) $item->absorb_damage_basis_points);
    }

    private function rarity(Item $item, $rarity)
    {
        if ($this->configuration->isWeapon($item)) {
            return new ItemStatBonuses(0, 0, 0, $this->wholePercent($rarity->weapon_accuracy_bonus_basis_points), 0, $this->decimalPercent($rarity->weapon_critical_bonus_basis_points), '0.00', 0);
        }
        if ($this->configuration->isDefensive($item)) {
            return new ItemStatBonuses(0, 0, 0, 0, $this->wholePercent($rarity->armor_evasion_bonus_basis_points), '0.00', $this->decimalFromHundredths($rarity->armor_speed_bonus_hundredths), (int) $rarity->armor_absorb_damage_bonus_basis_points);
        }
        return new ItemStatBonuses();
    }

    private function increase($base, $points)
    {
        if ($base < 0 || $points < 0 || ($base > 0 && $points > intdiv(PHP_INT_MAX, $base))) {
            throw new InvalidArgumentException('Invalid effective Item stat operands.');
        }
        return intdiv(($base * $points) + 5000, 10000);
    }

    private function wholePercent($basisPoints)
    {
        return intdiv((int) $basisPoints + 50, 100);
    }

    private function decimalPercent($basisPoints)
    {
        return $this->decimalFromHundredths((int) $basisPoints);
    }

    private function decimalFromHundredths($value)
    {
        return ItemStatBonuses::decimalFromHundredths((int) $value);
    }

    private function add(ItemStatBonuses $left, ItemStatBonuses $right)
    {
        return new ItemStatBonuses(
            $left->maxHealth() + $right->maxHealth(),
            $left->attack() + $right->attack(),
            $left->defense() + $right->defense(),
            $left->accuracy() + $right->accuracy(),
            $left->evasion() + $right->evasion(),
            ItemStatBonuses::decimalFromHundredths($left->criticalChanceHundredths() + $right->criticalChanceHundredths()),
            ItemStatBonuses::decimalFromHundredths($left->attackSpeedHundredths() + $right->attackSpeedHundredths()),
            $left->absorbDamageBasisPoints() + $right->absorbDamageBasisPoints()
        );
    }
}
