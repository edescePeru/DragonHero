<?php
namespace App\Domain\Shops;final class ShopOfferCategory{const WEAPONS='weapons';const ARMOR='armor';const CONSUMABLES='consumables';const MATERIALS='materials';const RECIPES='recipes';const EVENT='event';public static function values(){return[self::WEAPONS,self::ARMOR,self::CONSUMABLES,self::MATERIALS,self::RECIPES,self::EVENT];}private function __construct(){}}
