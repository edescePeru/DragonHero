<?php
namespace App\Domain\Combat\Manual\Rewards;
final class CombatPendingRewardStatus
{
    const PENDING = 'pending';
    const PENDING_CLAIM = 'pending_claim';
    const GRANTED = 'granted';
    const FORFEITED = 'forfeited';
    public static function pendingValues() { return [self::PENDING, self::PENDING_CLAIM]; }
    private function __construct() {}
}
