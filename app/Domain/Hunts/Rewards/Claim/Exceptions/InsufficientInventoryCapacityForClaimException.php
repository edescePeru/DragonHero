<?php
namespace App\Domain\Hunts\Rewards\Claim\Exceptions;
use App\Domain\Inventory\Capacity\Data\InventoryCapacityResult;use RuntimeException;
final class InsufficientInventoryCapacityForClaimException extends RuntimeException{private $capacity;public function __construct(InventoryCapacityResult $capacity){parent::__construct('No tienes espacio suficiente para reclamar todas las recompensas. Libera o amplía tu inventario e inténtalo nuevamente.');$this->capacity=$capacity;}public function capacity(){return$this->capacity;}}
