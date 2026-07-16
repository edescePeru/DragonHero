<?php
namespace App\Domain\Inventory\Instances;final class ItemInstanceStatus{const AVAILABLE='available';const EQUIPPED='equipped';public static function values(){return[self::AVAILABLE,self::EQUIPPED];}private function __construct(){}}
