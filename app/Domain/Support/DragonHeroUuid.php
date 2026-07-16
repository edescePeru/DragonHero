<?php
namespace App\Domain\Support;use Ramsey\Uuid\Uuid;
final class DragonHeroUuid{const NAMESPACE_UUID='8f4c1c72-7d7b-5d1a-9a4e-2d9c0f7b6a31';public static function versionFive(string $name):string{return(string)Uuid::uuid5(self::NAMESPACE_UUID,$name);}private function __construct(){}}
