<?php
namespace App\Domain\Inventory\Instances\Rarity;
final class ItemRarityRollResult
{
    private $rolledId,$rolledCode,$resolvedId,$resolvedCode,$roll,$version,$mapped,$reason,$fixed;
    public function __construct($rolledId,$rolledCode,int $resolvedId,string $resolvedCode,$roll,$version,bool $mapped,string $reason,bool $fixed){$this->rolledId=$rolledId;$this->rolledCode=$rolledCode;$this->resolvedId=$resolvedId;$this->resolvedCode=$resolvedCode;$this->roll=$roll;$this->version=$version;$this->mapped=$mapped;$this->reason=$reason;$this->fixed=$fixed;}
    public function rolledRarityId():?int{return$this->rolledId;}
    public function rolledRarityCode():?string{return$this->rolledCode;}
    public function resolvedRarityId():int{return$this->resolvedId;}
    public function resolvedRarityCode():string{return$this->resolvedCode;}
    public function rollPpm():?int{return$this->roll;}
    public function configurationVersion():?int{return$this->version;}
    public function mappingApplied():bool{return$this->mapped;}
    public function mappingReason():string{return$this->reason;}
    public function usedFixedRarity():bool{return$this->fixed;}
    public function metadata():array{return['version'=>1,'roll_ppm'=>$this->roll,'rolled_rarity_code'=>$this->rolledCode,'resolved_rarity_code'=>$this->resolvedCode,'mapping_applied'=>$this->mapped,'mapping_reason'=>$this->reason,'configuration_version'=>$this->version];}
}
