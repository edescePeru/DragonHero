<?php
namespace App\Domain\Combat\Factories;
use App\Domain\Combat\Data\CombatantStats;use App\Domain\Stats\DamageReductionCalculator;use App\Models\Monster;
final class MonsterCombatantFactory {private $reduction;public function __construct(DamageReductionCalculator $r){$this->reduction=$r;}public function make(Monster $m,$identifier=null){$identifier=$identifier===null?'monster:'.$m->id:$identifier;if(!is_string($identifier)||trim($identifier)==='')throw new \InvalidArgumentException('Monster identifier cannot be empty.');return new CombatantStats($identifier,$m->name,$m->max_health,$m->max_health,$m->attack,$m->defense,$m->accuracy_rate,$m->evasion_rate,$m->critical_chance,1.5,1.0,$this->reduction->calculate($m->defense));}}
