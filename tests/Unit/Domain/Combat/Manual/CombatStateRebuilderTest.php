<?php

namespace Tests\Unit\Domain\Combat\Manual;

use App\Domain\Combat\CombatSide;
use App\Domain\Combat\CombatTurnOrder;
use App\Domain\Combat\Manual\CombatStateRebuilder;
use App\Models\CombatParticipant;
use PHPUnit\Framework\TestCase;

class CombatStateRebuilderTest extends TestCase
{
    private function participant($identifier, $team, $position, $speed)
    {
        return new CombatParticipant([
            'team' => $team,
            'position' => $position,
            'source_identifier' => $identifier,
            'display_name' => $identifier,
            'current_hp' => 100,
            'max_hp' => 100,
            'stats_snapshot' => ['max_health' => 100, 'attack' => 10, 'defense' => 5, 'accuracy_rate' => 80, 'evasion_rate' => 5, 'critical_chance' => 5, 'critical_damage_multiplier' => 1.5, 'attack_speed' => $speed, 'damage_reduction_rate' => 4.76],
        ]);
    }

    public function test_it_delegates_speed_and_deterministic_ties_to_combat_turn_order()
    {
        $participants = collect([
            $this->participant('monster:2:2', CombatSide::ENEMIES, 2, 1),
            $this->participant('character:1', CombatSide::PLAYERS, 1, 1),
            $this->participant('monster:2:1', CombatSide::ENEMIES, 1, 2),
        ]);
        $rebuilder = new CombatStateRebuilder(new CombatTurnOrder());
        $this->assertSame(['monster:2:1', 'character:1', 'monster:2:2'], $rebuilder->initialOrder($participants));
    }
}
