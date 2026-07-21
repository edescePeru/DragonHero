<?php

namespace App\Domain\Hunts\Rewards;

use App\Domain\Hunts\Rewards\Data\HuntRewardValues;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Monster;
use InvalidArgumentException;

final class MonsterRewardValueGenerator
{
    private $random;

    public function __construct(RandomNumberGenerator $random) { $this->random = $random; }

    public function generate(Monster $monster): HuntRewardValues
    {
        $minimum = $this->safe($monster->gold_min);
        $maximum = $this->safe($monster->gold_max);
        $experience = $this->safe($monster->experience_reward);
        if ($minimum > $maximum) throw new InvalidArgumentException('Invalid Monster gold range.');
        $gold = $minimum === $maximum ? $minimum : $this->random->randomInt($minimum, $maximum);
        return new HuntRewardValues($gold, $experience);
    }

    private function safe($value)
    {
        if (is_int($value) && $value >= 0) return $value;
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
            $maximum = (string) PHP_INT_MAX;
            if (strlen($value) < strlen($maximum) || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) <= 0)) return (int) $value;
        }
        throw new InvalidArgumentException('Reward value exceeds the safe PHP integer range.');
    }
}
