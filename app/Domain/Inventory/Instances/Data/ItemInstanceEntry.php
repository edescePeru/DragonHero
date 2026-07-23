<?php
namespace App\Domain\Inventory\Instances\Data;
final class ItemInstanceEntry
{
    private $v;
    public function __construct(string $uuid,int $itemId,string $code,string $name,int $refinement,string $status,string $acquiredAt,int $rarityId=0,string $rarityCode='common',string $rarityName='Común',string $rarityStyle='neutral'){$this->v=func_get_args();$defaults=[0,'common','Común','neutral'];while(count($this->v)<11)$this->v[]=$defaults[count($this->v)-7];}
    public function uuid(){return$this->v[0];}
    public static function publicReferenceFor($uuid){return strtoupper(substr(str_replace('-','',(string)$uuid),-8));}
    public function publicReference(){return self::publicReferenceFor($this->v[0]);}
    public function toArray(){return['uuid'=>$this->v[0],'public_reference'=>$this->publicReference(),'item_id'=>$this->v[1],'item_code'=>$this->v[2],'item_name'=>$this->v[3],'refinement_level'=>$this->v[4],'status'=>$this->v[5],'acquired_at'=>$this->v[6],'rarity_id'=>$this->v[7],'rarity_code'=>$this->v[8],'rarity_name'=>$this->v[9],'rarity_visual_style'=>$this->v[10]];}
}
