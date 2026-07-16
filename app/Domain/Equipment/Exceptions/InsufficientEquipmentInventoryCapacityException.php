<?php
namespace App\Domain\Equipment\Exceptions;use DomainException;final class InsufficientEquipmentInventoryCapacityException extends DomainException{public function __construct(){parent::__construct('No tienes espacio disponible en la mochila para desequipar este objeto.');}}
