<?php
namespace App\Domain\Combat\Manual\Rewards\Data;
final class CombatRewardClaimResult
{
    private $values;
    public function __construct(array $values) { $this->values = $values; }
    public function toArray() { return $this->values; }
}
