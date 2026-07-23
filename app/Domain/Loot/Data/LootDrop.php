<?php
namespace App\Domain\Loot\Data;
use App\Domain\Inventory\Instances\Rarity\ItemRarityRollResult;
final class LootDrop
{
    private $itemId,$code,$name,$type,$quantity,$probability,$roll,$rarity;
    public function __construct($itemId,$code,$name,$type,$quantity,$probabilityPpm,$rollPpm,ItemRarityRollResult $rarity=null){$this->itemId=$itemId;$this->code=$code;$this->name=$name;$this->type=$type;$this->quantity=$quantity;$this->probability=$probabilityPpm;$this->roll=$rollPpm;$this->rarity=$rarity;}
    public function itemId(){return$this->itemId;}public function itemCode(){return$this->code;}public function itemName(){return$this->name;}public function itemType(){return$this->type;}public function quantity(){return$this->quantity;}public function configuredProbabilityPpm(){return$this->probability;}public function probabilityRollPpm(){return$this->roll;}
    public function itemRarityRollResult(){return$this->rarity;}public function resolvedItemRarityId(){return$this->rarity?$this->rarity->resolvedRarityId():null;}public function resolvedItemRarityCode(){return$this->rarity?$this->rarity->resolvedRarityCode():null;}
}
