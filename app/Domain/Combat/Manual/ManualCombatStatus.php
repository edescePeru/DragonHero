<?php
namespace App\Domain\Combat\Manual;
final class ManualCombatStatus
{
    const PENDING = 'pending'; const ACTIVE = 'active'; const WAITING_PLAYER = 'waiting_player';
    const WON = 'won'; const LOST = 'lost'; const ABANDONED = 'abandoned'; const EXPIRED = 'expired';
    public static function activeValues() { return [self::PENDING, self::ACTIVE, self::WAITING_PLAYER]; }
    public static function terminalValues() { return [self::WON, self::LOST, self::ABANDONED, self::EXPIRED]; }
}
