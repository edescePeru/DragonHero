<?php
namespace App\Domain\WorldCatalog;
use InvalidArgumentException;
use Illuminate\Support\Str;
final class WorldCatalogValidator {
    public function normalizeCode($code){$normalized=Str::snake(trim((string)$code)); if(!preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/',$normalized)){throw new InvalidArgumentException('Invalid catalog code.');} return $normalized;}
    public function assertStatus($value){$this->assertIn($value,CatalogStatus::values(),'status');}
    public function assertZoneType($value){$this->assertIn($value,ZoneType::values(),'zone type');}
    public function assertTravelType($value){$this->assertIn($value,TravelType::values(),'travel type');}
    public function assertItemType($value){$this->assertIn($value,ItemType::values(),'item type');}
    public function assertItemRarity($value){$this->assertIn($value,ItemRarity::values(),'item rarity');}
    public function assertLevelRange($minimum,$maximum){if((int)$minimum<1||($maximum!==null&&(int)$maximum<(int)$minimum)){throw new InvalidArgumentException('Invalid level range.');}}
    public function assertConnection($fromZoneId,$toZoneId){if((int)$fromZoneId===(int)$toZoneId){throw new InvalidArgumentException('A zone cannot connect to itself.');}}
    public function assertZoneMonster($weight,$minimum,$maximum){if((int)$weight<=0){throw new InvalidArgumentException('Weight must be greater than zero.');}$this->assertLevelRange($minimum,$maximum);}
    public function assertItemStack($isStackable,$maxStack){if($maxStack===null||(int)$maxStack<=0){throw new InvalidArgumentException('Every inventory item requires a positive max stack.');}if($isStackable&&(int)$maxStack<=1){throw new InvalidArgumentException('Stackable items require max stack greater than one.');}if(!$isStackable&&(int)$maxStack!==1){throw new InvalidArgumentException('Non-stackable items require max stack one.');}}
    private function assertIn($value,array $allowed,$label){if(!in_array($value,$allowed,true)){throw new InvalidArgumentException('Invalid '.$label.'.');}}
}
