<?php
namespace App\Domain\Shops;
use InvalidArgumentException;
final class ShopSaleSourceType{const STACK='stack';const INSTANCE='instance';private function __construct(){}public static function values(){return[self::STACK,self::INSTANCE];}public static function assertValid($value){if(!in_array($value,self::values(),true))throw new InvalidArgumentException('Invalid Shop sale source type.');return$value;}}
