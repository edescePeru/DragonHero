<?php
namespace App\Domain\Hunts\Data;final class HuntCombatEventResult{private $data;public function __construct(array $data){$this->data=$data;}public function toArray(){return array_merge([],$this->data);}}
