<?php

namespace App\Domain\Characters;

use App\Domain\Characters\Data\CharacterStats;
use App\Models\Character;
use App\Domain\Stats\DamageReductionCalculator;

final class CharacterStatsCalculator
{
    private $damageReduction;
    public function __construct(DamageReductionCalculator $damageReduction = null){$this->damageReduction=$damageReduction ?: new DamageReductionCalculator();}
    const CRITICAL_DAMAGE_MULTIPLIER = 1.50;
    const ATTACK_SPEED = 1.00;
    const DAMAGE_REDUCTION_CONSTANT = 100;
    const MAX_DAMAGE_REDUCTION_RATE = 75.00;
    const LOOT_BONUS = 0.00;
    const EXPERIENCE_BONUS = 0.00;
    const GOLD_BONUS = 0.00;

    const POWER_MAX_HEALTH_WEIGHT = 0.20;
    const POWER_ATTACK_WEIGHT = 3.00;
    const POWER_DEFENSE_WEIGHT = 2.00;
    const POWER_ACCURACY_WEIGHT = 0.50;
    const POWER_EVASION_WEIGHT = 1.50;
    const POWER_CRITICAL_CHANCE_WEIGHT = 2.00;
    const POWER_CRITICAL_DAMAGE_WEIGHT = 10.00;
    const POWER_ATTACK_SPEED_WEIGHT = 10.00;

    public function calculate(Character $character)
    {
        $maxHealth = (int) $character->base_max_health;
        $currentHealth = max(0, min((int) $character->current_health, $maxHealth));
        $attack = (int) $character->base_attack;
        $defense = (int) $character->base_defense;
        $accuracyRate = (float) $character->base_accuracy;
        $evasionRate = (float) $character->base_evasion;
        $criticalChance = $this->decimalToFloat($character->base_critical_rate);
        $damageReductionRate = $this->damageReduction->calculate($defense);

        $power = $this->calculatePower(
            $maxHealth,
            $attack,
            $defense,
            $accuracyRate,
            $evasionRate,
            $criticalChance,
            self::CRITICAL_DAMAGE_MULTIPLIER,
            self::ATTACK_SPEED,
            $damageReductionRate
        );

        return new CharacterStats(
            $maxHealth,
            $currentHealth,
            $attack,
            $defense,
            $accuracyRate,
            $evasionRate,
            $criticalChance,
            self::CRITICAL_DAMAGE_MULTIPLIER,
            self::ATTACK_SPEED,
            $damageReductionRate,
            self::LOOT_BONUS,
            self::EXPERIENCE_BONUS,
            self::GOLD_BONUS,
            $power
        );
    }

    private function decimalToFloat($value)
    {
        return (float) (string) $value;
    }

    private function calculatePower(
        $maxHealth,
        $attack,
        $defense,
        $accuracyRate,
        $evasionRate,
        $criticalChance,
        $criticalDamageMultiplier,
        $attackSpeed,
        $damageReductionRate
    ) {
        return $maxHealth * self::POWER_MAX_HEALTH_WEIGHT
            + $attack * self::POWER_ATTACK_WEIGHT
            + $defense * self::POWER_DEFENSE_WEIGHT
            + $accuracyRate * self::POWER_ACCURACY_WEIGHT
            + $evasionRate * self::POWER_EVASION_WEIGHT
            + $criticalChance * self::POWER_CRITICAL_CHANCE_WEIGHT
            + $criticalDamageMultiplier * self::POWER_CRITICAL_DAMAGE_WEIGHT
            + $attackSpeed * self::POWER_ATTACK_SPEED_WEIGHT
            + $damageReductionRate;
    }
}
