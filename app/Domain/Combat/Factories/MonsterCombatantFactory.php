<?php
namespace App\Domain\Combat\Factories;
use App\Domain\Combat\Data\CombatantStats;use App\Domain\Stats\DamageReductionCalculator;use App\Models\Monster;
final class MonsterCombatantFactory {private $reduction;public function __construct(DamageReductionCalculator $r){$this->reduction=$r;}public function make(Monster $m){return new CombatantStats('monster:'.$m->id,$m->name,$m->max_health,$m->max_health,$m->attack,$m->defense,$m->accuracy_rate,$m->evasion_rate,$m->critical_chance,1.5,1.0,$this->reduction->calculate($m->defense));}}
