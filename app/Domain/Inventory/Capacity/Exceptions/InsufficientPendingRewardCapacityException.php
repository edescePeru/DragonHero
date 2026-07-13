<?php
namespace App\Domain\Inventory\Capacity\Exceptions;
use App\Domain\Inventory\Capacity\Data\InventoryCapacityResult;
use RuntimeException;
final class InsufficientPendingRewardCapacityException extends RuntimeException{private $capacity;public function __construct(InventoryCapacityResult $capacity){parent::__construct('No hay espacio preventivo suficiente en el inventario para continuar la cacería.');$this->capacity=$capacity;}public function capacity(){return$this->capacity;}}
