<?php
namespace App\Domain\Hunts\Rewards\Data;
final class HuntRewardValues{private $gold;private $experience;public function __construct(int $gold,int $experience){if($gold<0||$experience<0)throw new \InvalidArgumentException('Reward values cannot be negative.');$this->gold=$gold;$this->experience=$experience;}public function gold():int{return$this->gold;}public function experience():int{return$this->experience;}}
