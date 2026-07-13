<?php
namespace App\Domain\Inventory\Capacity\Data;
final class InventorySlotResult{private $quantities;private $slots;public function __construct(array $quantities,$slots){$this->quantities=$quantities;$this->slots=$slots;}public function quantities(){$copy=[];foreach($this->quantities as $key=>$value)$copy[$key]=$value;return$copy;}public function slots(){return$this->slots;}}
