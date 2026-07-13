<?php
namespace App\Domain\Hunts\Sessions\Data;final class HuntingSessionTickResult{private $data;public function __construct(array $data){$this->data=$data;}public function toArray(){return array_merge([],$this->data);}}
