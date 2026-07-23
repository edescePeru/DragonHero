<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Combat\Data\CombatantStats;
use App\Domain\Combat\Factories\CharacterCombatantFactory;
use App\Domain\Combat\Factories\MonsterCombatantFactory;
use App\Models\Character;
use App\Models\Monster;

final class CombatParticipantSnapshotFactory
{
    private $calculator;
    private $characters;
    private $monsters;

    public function __construct(CharacterStatsCalculator $calculator, CharacterCombatantFactory $characters, MonsterCombatantFactory $monsters)
    {
        $this->calculator = $calculator;
        $this->characters = $characters;
        $this->monsters = $monsters;
    }

    public function forCharacter(Character $character)
    {
        $breakdown = $this->calculator->breakdown($character);
        $combatant = $this->characters->make($character, $breakdown->effective());

        return [
            'identifier' => $combatant->identifier(),
            'name' => $combatant->name(),
            'max_hp' => $combatant->maxHealth(),
            'stats' => $this->stats($combatant, [
                'schema_version' => 1,
                'source_type' => CombatParticipantType::CHARACTER,
                'source_id' => (int) $character->id,
                'character_stats' => $breakdown->toSnapshotArray(),
            ]),
        ];
    }

    public function forMonster(Monster $monster, $position)
    {
        $identifier = 'monster:'.$monster->id.':'.(int) $position;
        $combatant = $this->monsters->make($monster, $identifier);

        return [
            'identifier' => $combatant->identifier(),
            'name' => $combatant->name(),
            'max_hp' => $combatant->maxHealth(),
            'stats' => $this->stats($combatant, [
                'schema_version' => 1,
                'source_type' => CombatParticipantType::MONSTER,
                'source_id' => (int) $monster->id,
                'source_code' => (string) $monster->code,
            ]),
        ];
    }

    private function stats(CombatantStats $stats, array $context)
    {
        return array_merge($context, [
            'max_health' => $stats->maxHealth(),
            'attack' => $stats->attack(),
            'defense' => $stats->defense(),
            'accuracy_rate' => $stats->accuracyRate(),
            'evasion_rate' => $stats->evasionRate(),
            'critical_chance' => $stats->criticalChance(),
            'critical_damage_multiplier' => $stats->criticalDamageMultiplier(),
            'attack_speed' => $stats->attackSpeed(),
            'damage_reduction_rate' => $stats->damageReductionRate(),
            'absorb_damage_basis_points' => $stats->absorbDamageBasisPoints(),
            'combat_mitigation_config' => $stats->mitigationConfig()->toArray(),
        ]);
    }
}
