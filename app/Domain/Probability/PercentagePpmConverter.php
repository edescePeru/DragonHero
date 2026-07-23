<?php

namespace App\Domain\Probability;

use InvalidArgumentException;

final class PercentagePpmConverter
{
    const MAX_PPM = 1000000;

    public function toPpm(string $percentage): int
    {
        if (!preg_match('/^(?:100(?:\.0{1,4})?|(?:0|[1-9][0-9]?)(?:\.[0-9]{1,4})?)$/', $percentage)) {
            throw new InvalidArgumentException('Invalid percentage format.');
        }

        $parts = explode('.', $percentage, 2);
        $whole = (int) $parts[0];
        $fraction = isset($parts[1]) ? (int) str_pad($parts[1], 4, '0') : 0;
        $ppm = $whole * 10000 + $fraction;

        if ($ppm < 0 || $ppm > self::MAX_PPM) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100.');
        }

        return $ppm;
    }

    public function toPercentageString(int $ppm): string
    {
        if ($ppm < 0 || $ppm > self::MAX_PPM) {
            throw new InvalidArgumentException('PPM value must be between 0 and 1000000.');
        }

        return intdiv($ppm, 10000).'.'.str_pad((string) ($ppm % 10000), 4, '0', STR_PAD_LEFT);
    }
}
