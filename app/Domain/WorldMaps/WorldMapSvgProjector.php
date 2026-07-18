<?php

namespace App\Domain\WorldMaps;

use InvalidArgumentException;

final class WorldMapSvgProjector
{
    public function project(array $polygonPoints, $width, $height)
    {
        $width = (int) $width;
        $height = (int) $height;

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('World map SVG projection requires positive dimensions.');
        }

        if (!isset($polygonPoints['coordinate_system'])
            || $polygonPoints['coordinate_system'] !== 'normalized'
            || !isset($polygonPoints['points'])
            || !is_array($polygonPoints['points'])) {
            throw new InvalidArgumentException('World map SVG projection requires normalized points.');
        }

        return collect($polygonPoints['points'])->map(function ($point) use ($width, $height) {
            if (!is_array($point) || !array_key_exists('x', $point) || !array_key_exists('y', $point)) {
                throw new InvalidArgumentException('World map SVG projection requires x and y coordinates.');
            }

            return $this->number((float) $point['x'] * $width).','.$this->number((float) $point['y'] * $height);
        })->implode(' ');
    }

    private function number($value)
    {
        $formatted = number_format($value, 6, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
