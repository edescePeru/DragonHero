<?php
namespace App\Domain\WorldCatalog;
final class ZoneType { const TOWN='town'; const FIELD='field'; const FOREST='forest'; const MINE='mine'; const DUNGEON_ENTRANCE='dungeon_entrance'; const SPECIAL='special'; private function __construct(){} public static function values(){return [self::TOWN,self::FIELD,self::FOREST,self::MINE,self::DUNGEON_ENTRANCE,self::SPECIAL];} }
