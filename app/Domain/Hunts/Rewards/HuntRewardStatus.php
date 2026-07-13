<?php
namespace App\Domain\Hunts\Rewards;final class HuntRewardStatus{const PENDING='pending';const CLAIMED='claimed';public static function values(){return[self::PENDING,self::CLAIMED];}}
