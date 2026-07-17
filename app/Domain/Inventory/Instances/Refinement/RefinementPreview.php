<?php
namespace App\Domain\Inventory\Instances\Refinement;
final class RefinementPreview { private $data; public function __construct(array $data) { $this->data = $data; } public function toArray() { return $this->data; } }
