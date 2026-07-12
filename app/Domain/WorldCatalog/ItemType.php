<?php
namespace App\Domain\WorldCatalog;
final class ItemType { const MATERIAL='material'; const CONSUMABLE='consumable'; const EQUIPMENT='equipment'; const QUEST='quest'; const KEY='key'; const CURRENCY_ITEM='currency_item'; const DRAGON_MATERIAL='dragon_material'; private function __construct(){} public static function values(){return [self::MATERIAL,self::CONSUMABLE,self::EQUIPMENT,self::QUEST,self::KEY,self::CURRENCY_ITEM,self::DRAGON_MATERIAL];} }
