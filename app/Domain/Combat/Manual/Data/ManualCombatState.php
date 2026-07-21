<?php

namespace App\Domain\Combat\Manual\Data;

final class ManualCombatState
{
    private $data;

    public function __construct(array $data) { $this->data = $data; }
    public function toArray() { return array_merge([], $this->data); }
    public function id() { return (int) $this->data['combat_id']; }
}
