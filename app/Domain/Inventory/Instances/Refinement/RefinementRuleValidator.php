<?php

namespace App\Domain\Inventory\Instances\Refinement;

use App\Domain\Inventory\Instances\ItemInstanceLimits;
use App\Domain\Inventory\ItemClassification;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Item;
use App\Models\RefinementLevel;
use InvalidArgumentException;

final class RefinementRuleValidator
{
    private $classification;
    public function __construct(ItemClassification $classification) { $this->classification = $classification; }
    public function validate(RefinementLevel $rule)
    {
        if ((int) $rule->from_level < ItemInstanceLimits::MIN_REFINEMENT_LEVEL || (int) $rule->to_level !== (int) $rule->from_level + 1 || (int) $rule->to_level > ItemInstanceLimits::MAX_REFINEMENT_LEVEL) throw new InvalidArgumentException('Refinement levels must be consecutive and within limits.');
        if ((int) $rule->success_chance_basis_points !== 10000) throw new InvalidArgumentException('Refinement v1 requires a 100% success chance.');
        if ($rule->failure_behavior !== RefinementFailureBehavior::KEEP_LEVEL) throw new InvalidArgumentException('Invalid refinement failure behavior.');
        if (! in_array($rule->status, CatalogStatus::values(), true)) throw new InvalidArgumentException('Invalid refinement rule status.');
        $this->safeNonNegative($rule->gold_cost);
        return $rule;
    }
    public function validateMaterial(Item $item, $quantity)
    {
        if ($item->status !== CatalogStatus::ACTIVE || $this->classification->classify($item) !== ItemClassification::STACKABLE) throw new InvalidArgumentException('Refinement materials must be active coherent stackable Items.');
        if ($this->safeNonNegative($quantity) < 1) throw new InvalidArgumentException('Refinement material quantity must be positive.');
    }
    private function safeNonNegative($value)
    {
        if (is_int($value) && $value >= 0) return $value;
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value) && strlen($value) <= strlen((string) PHP_INT_MAX) && (strlen($value) < strlen((string) PHP_INT_MAX) || strcmp($value, (string) PHP_INT_MAX) <= 0)) return (int) $value;
        throw new InvalidArgumentException('Value exceeds the safe PHP integer range.');
    }
}
