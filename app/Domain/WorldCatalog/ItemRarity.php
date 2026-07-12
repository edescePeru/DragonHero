<?php
namespace App\Domain\WorldCatalog;
final class ItemRarity { const COMMON='common'; const UNCOMMON='uncommon'; const RARE='rare'; const EPIC='epic'; const LEGENDARY='legendary'; const DRACONIC='draconic'; private function __construct(){} public static function values(){return [self::COMMON,self::UNCOMMON,self::RARE,self::EPIC,self::LEGENDARY,self::DRACONIC];} }
