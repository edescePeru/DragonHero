<?php
namespace App\Domain\Hunts;final class HuntEnemyStatus{const ALIVE='alive';const DEFEATED='defeated';public static function fromFinalHealth($health){return (int)$health<=0?self::DEFEATED:self::ALIVE;}public static function values(){return[self::ALIVE,self::DEFEATED];}}
