<?php
namespace App\Domain\Combat\Manual;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
final class ManualCombatExpirationPolicy
{
    const DEFAULT_MINUTES=30;
    public function minutes(){ $value=config('game.manual_combat.expiration_minutes',self::DEFAULT_MINUTES); if(is_int($value)&&$value>0)return$value; if(is_string($value)&&preg_match('/^[1-9][0-9]*$/',$value))return(int)$value; return self::DEFAULT_MINUTES; }
    public function expiresAt(CombatSession $combat){$activity=$combat->last_action_at?:$combat->started_at;if(!$activity)return null;return CarbonImmutable::instance($activity)->addMinutes($this->minutes());}
    public function canExpire(CombatSession $combat){return in_array($combat->status,[ManualCombatStatus::ACTIVE,ManualCombatStatus::WAITING_PLAYER],true);}
}
