<?php
namespace App\Domain\WorldMaps;final class WorldMapAreaStatus{const DRAFT='draft';const ACTIVE='active';const INACTIVE='inactive';public static function values(){return[self::DRAFT,self::ACTIVE,self::INACTIVE];}}
