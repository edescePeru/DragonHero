<?php
namespace App\Domain\Combat;final class CombatStateStatus {const IN_PROGRESS='in_progress';public static function values(){return[self::IN_PROGRESS,CombatResultStatus::CHARACTER_VICTORY,CombatResultStatus::MONSTER_VICTORY,CombatResultStatus::DRAW];}}
