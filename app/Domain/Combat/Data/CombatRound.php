<?php
namespace App\Domain\Combat\Data;
use InvalidArgumentException;
final class CombatRound {private $number;private $actions;public function __construct($number,array $actions){foreach($actions as $a)if(!$a instanceof CombatAction)throw new InvalidArgumentException();$this->number=(int)$number;$this->actions=array_values($actions);}public function roundNumber(){return$this->number;}public function actions(){return array_slice($this->actions,0);}}
