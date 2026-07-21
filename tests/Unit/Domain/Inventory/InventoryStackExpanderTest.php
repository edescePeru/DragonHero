<?php

namespace Tests\Unit\Domain\Inventory;

use App\Domain\Inventory\InventoryStackExpander;
use PHPUnit\Framework\TestCase;

class InventoryStackExpanderTest extends TestCase
{
    /** @dataProvider stackCases */
    public function test_expands_aggregated_quantity_into_visual_slots($quantity, array $expected)
    {
        $this->assertSame($expected, (new InventoryStackExpander())->expand($quantity, 99));
    }

    public function stackCases()
    {
        return [
            'one' => [1, [1]],
            'full stack' => [99, [99]],
            'one over' => [100, [99, 1]],
            'one hundred three' => [103, [99, 4]],
            'two full stacks' => [198, [99, 99]],
            'two full and one' => [199, [99, 99, 1]],
        ];
    }
}
