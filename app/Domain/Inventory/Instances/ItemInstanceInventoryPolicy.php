<?php
namespace App\Domain\Inventory\Instances;use InvalidArgumentException;
final class ItemInstanceInventoryPolicy{public function occupiesInventory(string $status):bool{if($status===ItemInstanceStatus::AVAILABLE)return true;throw new InvalidArgumentException('Unsupported ItemInstance inventory status.');}}
