<?php
namespace Tests\Unit\Domain\WorldCatalog;
use App\Domain\WorldCatalog\WorldCatalogValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
class WorldCatalogValidatorTest extends TestCase {
 private function validator(){return new WorldCatalogValidator();}
 public function test_code_is_normalized_and_validated(){ $this->assertSame('kingdom_of_valtheria',$this->validator()->normalizeCode(' Kingdom of Valtheria ')); }
 public function test_zone_cannot_connect_to_itself(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertConnection(1,1); }
 public function test_weight_must_be_positive(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertZoneMonster(0,1,5); }
 public function test_maximum_level_cannot_be_lower_than_minimum(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertLevelRange(5,4); }
 public function test_stackable_item_requires_positive_max_stack(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertItemStack(true,null); }
 public function test_non_stackable_item_requires_null_max_stack(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertItemStack(false,1); }
 public function test_invalid_controlled_values_are_rejected(){ $this->expectException(InvalidArgumentException::class);$this->validator()->assertStatus('deleted'); }
 public function test_all_controlled_types_reject_unknown_values(){
  $checks=[function(){$this->validator()->assertZoneType('unknown');},function(){$this->validator()->assertTravelType('unknown');},function(){$this->validator()->assertItemType('unknown');},function(){$this->validator()->assertItemRarity('unknown');}];
  foreach($checks as $check){$rejected=false;try{$check();}catch(InvalidArgumentException $e){$rejected=true;}$this->assertTrue($rejected);}
 }
}
