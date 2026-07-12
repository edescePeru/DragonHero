<?php

namespace Tests\Unit\Domain\Characters;

use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Characters\Data\CharacterStats;
use App\Models\Character;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CharacterStatsCalculatorTest extends TestCase
{
    private function character(array $overrides = [])
    {
        $character = new Character();
        $character->setRawAttributes(array_merge([
            'base_max_health' => 100,
            'current_health' => 100,
            'base_attack' => 10,
            'base_defense' => 5,
            'base_accuracy' => 80,
            'base_evasion' => 5,
            'base_critical_rate' => '5.00',
        ], $overrides));

        return $character;
    }

    private function calculate(array $overrides = [])
    {
        return (new CharacterStatsCalculator())->calculate($this->character($overrides));
    }

    public function test_it_calculates_all_initial_stats()
    {
        $stats = $this->calculate();
        $this->assertSame(100, $stats->maxHealth());
        $this->assertSame(100, $stats->currentHealth());
        $this->assertSame(10, $stats->attack());
        $this->assertSame(5, $stats->defense());
        $this->assertSame(80.0, $stats->accuracyRate());
        $this->assertSame(5.0, $stats->evasionRate());
        $this->assertSame(5.0, $stats->criticalChance());
    }

    public function test_current_health_is_limited_to_max_health()
    {
        $this->assertSame(100, $this->calculate(['current_health' => 250])->currentHealth());
    }

    public function test_negative_current_health_becomes_zero()
    {
        $this->assertSame(0, $this->calculate(['current_health' => -20])->currentHealth());
    }

    public function test_critical_chance_is_converted_from_decimal_string()
    {
        $this->assertSame(12.35, $this->calculate(['base_critical_rate' => '12.345'])->criticalChance());
    }

    public function test_critical_damage_multiplier_is_one_point_fifty()
    {
        $this->assertSame(1.5, $this->calculate()->criticalDamageMultiplier());
    }

    public function test_attack_speed_is_one_point_zero()
    {
        $this->assertSame(1.0, $this->calculate()->attackSpeed());
    }

    public function test_damage_reduction_uses_the_provisional_formula()
    {
        $this->assertSame(4.76, $this->calculate()->damageReductionRate());
    }

    public function test_damage_reduction_respects_the_maximum()
    {
        $this->assertSame(75.0, $this->calculate(['base_defense' => 1000])->damageReductionRate());
    }

    public function test_power_uses_the_complete_unrounded_formula()
    {
        $this->assertSame(147, $this->calculate()->power());
    }

    public function test_future_bonuses_start_at_zero()
    {
        $stats = $this->calculate();
        $this->assertSame(0.0, $stats->lootBonus());
        $this->assertSame(0.0, $stats->experienceBonus());
        $this->assertSame(0.0, $stats->goldBonus());
    }

    public function test_character_stats_is_immutable_by_structure()
    {
        $reflection = new ReflectionClass(CharacterStats::class);
        $this->assertTrue($reflection->isFinal());
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isPrivate());
        }
        foreach ($reflection->getMethods() as $method) {
            $this->assertFalse(strpos($method->getName(), 'set') === 0);
        }
    }
}
