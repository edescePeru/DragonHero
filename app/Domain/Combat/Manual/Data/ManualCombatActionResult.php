<?php
namespace App\Domain\Combat\Manual\Data;
final class ManualCombatActionResult { private $data; public function __construct(array $data){$this->data=$data;} public function toArray(){return array_merge([],$this->data);} }
