<?php
namespace App\Domain\Combat\Factories;
use App\Domain\Characters\Data\CharacterStats;use App\Domain\Combat\Data\CombatantStats;use App\Models\Character;
final class CharacterCombatantFactory {public function make(Character $c,CharacterStats $s){return new CombatantStats('character:'.$c->id,$c->name,$s->maxHealth(),$s->currentHealth(),$s->attack(),$s->defense(),$s->accuracyRate(),$s->evasionRate(),$s->criticalChance(),$s->criticalDamageMultiplier(),$s->attackSpeed(),$s->damageReductionRate());}}
