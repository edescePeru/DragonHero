<?php
namespace App\Domain\Equipment\Data;
final class CharacterEquipmentStats{private $bonuses;private $sources;public function __construct(ItemStatBonuses $bonuses,array $sources){$this->bonuses=$bonuses;$this->sources=array_values($sources);}public function bonuses(){return$this->bonuses;}public function sources(){return array_map(function($source){return array_merge([],$source,['bonuses'=>array_merge([],$source['bonuses'])]);},$this->sources);}}
