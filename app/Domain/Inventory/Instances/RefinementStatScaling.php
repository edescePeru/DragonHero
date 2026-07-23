<?php
namespace App\Domain\Inventory\Instances;
use InvalidArgumentException;
final class RefinementStatScaling{private const CURVE=[0=>0,1=>100,2=>200,3=>300,4=>400,5=>500,6=>600,7=>700,8=>800,9=>900,10=>1000,11=>2000,12=>3000,13=>4000,14=>4500,15=>5000];public function basisPoints($level){$level=(int)$level;if(!array_key_exists($level,self::CURVE))throw new InvalidArgumentException('Invalid refinement scaling level.');return self::CURVE[$level];}public function curve(){return self::CURVE;}}
