<?php
namespace App\Domain\Shops;final class ShopOfferVisibility{const VISIBLE='visible';const HIDDEN='hidden';public static function values(){return[self::VISIBLE,self::HIDDEN];}private function __construct(){}}
