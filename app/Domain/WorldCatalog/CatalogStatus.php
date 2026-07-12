<?php
namespace App\Domain\WorldCatalog;
final class CatalogStatus { const ACTIVE='active'; const INACTIVE='inactive'; const HIDDEN='hidden'; private function __construct(){} public static function values(){return [self::ACTIVE,self::INACTIVE,self::HIDDEN];} }
