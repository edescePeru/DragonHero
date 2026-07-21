<?php
namespace App\Domain\Combat\Manual\Rewards\Exceptions;
use RuntimeException;
final class CombatRewardDeliveryUnavailableException extends RuntimeException
{
    private $capacity;
    public function __construct($capacity) { parent::__construct('Inventory capacity is insufficient for these combat rewards.'); $this->capacity = $capacity; }
    public function capacity() { return $this->capacity; }
}
