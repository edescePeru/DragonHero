<?php
namespace App\Domain\Inventory\Data;
final class InventoryEntry {
 private $itemId; private $itemCode; private $itemName; private $itemType; private $itemRarity; private $totalQuantity; private $lockedQuantity; private $availableQuantity;
 public function __construct($itemId,$itemCode,$itemName,$itemType,$itemRarity,$totalQuantity,$lockedQuantity){$this->itemId=(int)$itemId;$this->itemCode=(string)$itemCode;$this->itemName=(string)$itemName;$this->itemType=(string)$itemType;$this->itemRarity=(string)$itemRarity;$this->totalQuantity=(int)$totalQuantity;$this->lockedQuantity=(int)$lockedQuantity;$this->availableQuantity=$this->totalQuantity-$this->lockedQuantity;}
 public function itemId(){return $this->itemId;} public function itemCode(){return $this->itemCode;} public function itemName(){return $this->itemName;} public function itemType(){return $this->itemType;} public function itemRarity(){return $this->itemRarity;} public function totalQuantity(){return $this->totalQuantity;} public function lockedQuantity(){return $this->lockedQuantity;} public function availableQuantity(){return $this->availableQuantity;}
}
