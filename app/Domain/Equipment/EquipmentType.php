<?php
namespace App\Domain\Equipment;use InvalidArgumentException;
final class EquipmentType{const WEAPON='weapon';const HELMET='helmet';const ARMOR='armor';const GLOVES='gloves';const BOOTS='boots';const NECKLACE='necklace';const RING='ring';public static function all(){return[self::WEAPON,self::HELMET,self::ARMOR,self::GLOVES,self::BOOTS,self::NECKLACE,self::RING];}public static function assertValid($value){if(!is_string($value)||!in_array($value,self::all(),true))throw new InvalidArgumentException('Invalid equipment type.');return$value;}private function __construct(){}}
