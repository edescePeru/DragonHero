<?php
namespace App\Domain\Combat\Manual\Data;
final class ManualCombatLifecycleResult{private $values;public function __construct(array $values){$this->values=$values;}public function toArray(){return$this->values;}}
