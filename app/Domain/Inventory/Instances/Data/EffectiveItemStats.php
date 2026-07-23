<?php

namespace App\Domain\Inventory\Instances\Data;

use App\Domain\Equipment\Data\ItemStatBonuses;

final class EffectiveItemStats
{
    private $base;
    private $refinement;
    private $rarity;
    private $total;
    private $basisPoints;

    public function __construct(ItemStatBonuses $base, ItemStatBonuses $refinement, ItemStatBonuses $rarity, ItemStatBonuses $total, int $basisPoints)
    {
        $this->base = $base;
        $this->refinement = $refinement;
        $this->rarity = $rarity;
        $this->total = $total;
        $this->basisPoints = $basisPoints;
    }

    public function base() { return $this->base; }
    public function refinement() { return $this->refinement; }
    public function rarity() { return $this->rarity; }
    public function total() { return $this->total; }
    public function basisPoints() { return $this->basisPoints; }
}
