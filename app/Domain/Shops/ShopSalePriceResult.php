<?php
namespace App\Domain\Shops;
final class ShopSalePriceResult{private $unitGold;private $quantity;private $totalGold;public function __construct($unitGold,$quantity,$totalGold){$this->unitGold=$unitGold;$this->quantity=$quantity;$this->totalGold=$totalGold;}public function unitGold(){return$this->unitGold;}public function quantity(){return$this->quantity;}public function totalGold(){return$this->totalGold;}}
