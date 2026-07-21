<?php
namespace App\Domain\Combat\Manual;
final class CombatParticipantType { const CHARACTER = 'character'; const MONSTER = 'monster'; public static function values() { return [self::CHARACTER, self::MONSTER]; } }
