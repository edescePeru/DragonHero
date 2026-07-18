<?php

namespace Tests\Unit\Domain\WorldMaps;

use App\Domain\WorldMaps\WorldMapSvgProjector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorldMapSvgProjectorTest extends TestCase
{
    public function test_it_projects_normalized_points_using_real_map_dimensions()
    {
        $points = [
            'coordinate_system' => 'normalized',
            'points' => [
                ['x' => 0, 'y' => 0],
                ['x' => 0.5, 'y' => 0.5],
                ['x' => 1, 'y' => 1],
            ],
        ];

        $this->assertSame('0,0 836,470.5 1672,941', (new WorldMapSvgProjector())->project($points, 1672, 941));
    }

    public function test_it_rejects_non_normalized_geometry()
    {
        $this->expectException(InvalidArgumentException::class);

        (new WorldMapSvgProjector())->project(['coordinate_system' => 'pixels', 'points' => []], 1672, 941);
    }
}
