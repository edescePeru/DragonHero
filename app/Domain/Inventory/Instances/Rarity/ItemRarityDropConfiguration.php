<?php
namespace App\Domain\Inventory\Instances\Rarity;
use App\Domain\Inventory\Instances\ItemRarityCode;
use InvalidArgumentException;
final class ItemRarityDropConfiguration
{
    const TOTAL_PPM=1000000; private $probabilities; private $version;
    public function __construct(array $probabilities,int $version){$sum=0;foreach(ItemRarityCode::values() as $code){if(!isset($probabilities[$code])||!is_int($probabilities[$code])||$probabilities[$code]<0||$probabilities[$code]>self::TOTAL_PPM)throw new InvalidArgumentException('Invalid rarity probability.');$sum+=$probabilities[$code];}if($sum!==self::TOTAL_PPM)throw new InvalidArgumentException('Rarity probabilities must total 1000000 PPM.');if($version<1)throw new InvalidArgumentException('Invalid configuration version.');$this->probabilities=$probabilities;$this->version=$version;}
    public function probability(string $code):int{ItemRarityCode::assert($code);return$this->probabilities[$code];}
    public function probabilities():array{return$this->probabilities;}
    public function version():int{return$this->version;}
}
