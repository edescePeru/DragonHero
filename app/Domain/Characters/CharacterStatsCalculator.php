<?php
namespace App\Domain\Characters;
use App\Domain\Characters\Data\CharacterStats;
use App\Domain\Characters\Data\CharacterStatsBreakdown;
use App\Domain\Equipment\CharacterEquipmentStatsProvider;
use App\Domain\Equipment\Data\CharacterEquipmentStats;
use App\Domain\Equipment\Data\ItemStatBonuses;
use App\Domain\Stats\DamageReductionCalculator;
use App\Models\Character;
use InvalidArgumentException;
final class CharacterStatsCalculator{
 const CRITICAL_DAMAGE_MULTIPLIER=1.50;const ATTACK_SPEED=1.00;const LOOT_BONUS=0.00;const EXPERIENCE_BONUS=0.00;const GOLD_BONUS=0.00;
 const POWER_MAX_HEALTH_WEIGHT=0.20;const POWER_ATTACK_WEIGHT=3.00;const POWER_DEFENSE_WEIGHT=2.00;const POWER_ACCURACY_WEIGHT=0.50;const POWER_EVASION_WEIGHT=1.50;const POWER_CRITICAL_CHANCE_WEIGHT=2.00;const POWER_CRITICAL_DAMAGE_WEIGHT=10.00;const POWER_ATTACK_SPEED_WEIGHT=10.00;
 private $damageReduction;private $equipment;
 public function __construct(DamageReductionCalculator $damageReduction=null,CharacterEquipmentStatsProvider $equipment=null){$this->damageReduction=$damageReduction?:new DamageReductionCalculator();$this->equipment=$equipment;}
 public function calculate(Character $character){return$this->breakdown($character)->effective();}
 public function breakdown(Character $character){$equipment=$this->equipment?$this->equipment->snapshot($character):new CharacterEquipmentStats(new ItemStatBonuses(),[]);$bonus=$equipment->bonuses();$base=$this->build((int)$character->base_max_health,(int)$character->current_health,(int)$character->base_attack,(int)$character->base_defense,(float)$character->base_accuracy,(float)$character->base_evasion,$this->decimal($character->base_critical_rate),self::ATTACK_SPEED);$effective=$this->build($this->add((int)$character->base_max_health,$bonus->maxHealth()),(int)$character->current_health,$this->add((int)$character->base_attack,$bonus->attack()),$this->add((int)$character->base_defense,$bonus->defense()),$this->add((int)$character->base_accuracy,$bonus->accuracy()),$this->add((int)$character->base_evasion,$bonus->evasion()),$this->decimal($character->base_critical_rate)+$bonus->criticalChance(),self::ATTACK_SPEED+$bonus->attackSpeed());return new CharacterStatsBreakdown($base,$equipment,$effective);}
 private function build($maxHealth,$currentHealth,$attack,$defense,$accuracy,$evasion,$critical,$speed){if($maxHealth<=0||$attack<0||$defense<0||$accuracy<0||$evasion<0||$critical<0||$speed<=0)throw new InvalidArgumentException('Invalid Character statistics.');$current=max(0,min($currentHealth,$maxHealth));$reduction=$this->damageReduction->calculate($defense);$power=$maxHealth*self::POWER_MAX_HEALTH_WEIGHT+$attack*self::POWER_ATTACK_WEIGHT+$defense*self::POWER_DEFENSE_WEIGHT+$accuracy*self::POWER_ACCURACY_WEIGHT+$evasion*self::POWER_EVASION_WEIGHT+$critical*self::POWER_CRITICAL_CHANCE_WEIGHT+self::CRITICAL_DAMAGE_MULTIPLIER*self::POWER_CRITICAL_DAMAGE_WEIGHT+$speed*self::POWER_ATTACK_SPEED_WEIGHT+$reduction;return new CharacterStats($maxHealth,$current,$attack,$defense,$accuracy,$evasion,$critical,self::CRITICAL_DAMAGE_MULTIPLIER,$speed,$reduction,self::LOOT_BONUS,self::EXPERIENCE_BONUS,self::GOLD_BONUS,$power);}
 private function add($base,$bonus){if($base<0||$bonus<0||$bonus>PHP_INT_MAX-$base)throw new InvalidArgumentException('Character statistic overflow.');return$base+$bonus;}
 private function decimal($value){return(float)(string)$value;}
}
