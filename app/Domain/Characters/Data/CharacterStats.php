<?php

namespace App\Domain\Characters\Data;

final class CharacterStats
{
    private $maxHealth;
    private $currentHealth;
    private $attack;
    private $defense;
    private $accuracyRate;
    private $evasionRate;
    private $criticalChance;
    private $criticalDamageMultiplier;
    private $attackSpeed;
    private $damageReductionRate;
    private $lootBonus;
    private $experienceBonus;
    private $goldBonus;
    private $power;

    public function __construct(
        $maxHealth,
        $currentHealth,
        $attack,
        $defense,
        $accuracyRate,
        $evasionRate,
        $criticalChance,
        $criticalDamageMultiplier,
        $attackSpeed,
        $damageReductionRate,
        $lootBonus,
        $experienceBonus,
        $goldBonus,
        $power
    ) {
        $this->maxHealth = (int) $maxHealth;
        $this->currentHealth = (int) $currentHealth;
        $this->attack = (int) $attack;
        $this->defense = (int) $defense;
        $this->accuracyRate = round((float) $accuracyRate, 2);
        $this->evasionRate = round((float) $evasionRate, 2);
        $this->criticalChance = round((float) $criticalChance, 2);
        $this->criticalDamageMultiplier = round((float) $criticalDamageMultiplier, 2);
        $this->attackSpeed = round((float) $attackSpeed, 2);
        $this->damageReductionRate = round((float) $damageReductionRate, 2);
        $this->lootBonus = round((float) $lootBonus, 2);
        $this->experienceBonus = round((float) $experienceBonus, 2);
        $this->goldBonus = round((float) $goldBonus, 2);
        $this->power = (int) round($power);
    }

    public function maxHealth() { return $this->maxHealth; }
    public function currentHealth() { return $this->currentHealth; }
    public function attack() { return $this->attack; }
    public function defense() { return $this->defense; }
    public function accuracyRate() { return $this->accuracyRate; }
    public function evasionRate() { return $this->evasionRate; }
    public function criticalChance() { return $this->criticalChance; }
    public function criticalDamageMultiplier() { return $this->criticalDamageMultiplier; }
    public function attackSpeed() { return $this->attackSpeed; }
    public function damageReductionRate() { return $this->damageReductionRate; }
    public function lootBonus() { return $this->lootBonus; }
    public function experienceBonus() { return $this->experienceBonus; }
    public function goldBonus() { return $this->goldBonus; }
    public function power() { return $this->power; }
}
