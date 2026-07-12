<?php
namespace App\Domain\Inventory\Exceptions;
use RuntimeException;
class InsufficientItemQuantityException extends RuntimeException {
 const INSUFFICIENT_AVAILABLE_QUANTITY='insufficient_available_quantity'; const INSUFFICIENT_LOCKED_QUANTITY='insufficient_locked_quantity'; private $reasonCode;
 public function __construct($reasonCode){$this->reasonCode=$reasonCode;parent::__construct($reasonCode);}
 public function reasonCode(){return $this->reasonCode;}
}
