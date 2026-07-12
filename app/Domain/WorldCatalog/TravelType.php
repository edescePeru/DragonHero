<?php
namespace App\Domain\WorldCatalog;
final class TravelType { const ROAD='road'; const PATH='path'; const GATE='gate'; const PORTAL='portal'; const SHIP='ship'; const SPECIAL='special'; private function __construct(){} public static function values(){return [self::ROAD,self::PATH,self::GATE,self::PORTAL,self::SHIP,self::SPECIAL];} }
