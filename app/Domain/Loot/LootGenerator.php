<?php
namespace App\Domain\Loot;
use App\Domain\Inventory\ItemClassification;use App\Domain\Inventory\Instances\Rarity\ItemRarityRoller;use App\Domain\Loot\Data\LootDrop;use App\Domain\Loot\Data\LootResult;use App\Domain\Random\RandomNumberGenerator;use App\Domain\WorldCatalog\CatalogStatus;use App\Models\Monster;use App\Models\MonsterLootEntry;use Illuminate\Support\Collection;use InvalidArgumentException;
final class LootGenerator
{
    private $rng,$classification,$rarities;
    public function __construct(RandomNumberGenerator $rng,ItemClassification $classification,ItemRarityRoller $rarities=null){$this->rng=$rng;$this->classification=$classification;$this->rarities=$rarities?:new ItemRarityRoller($rng,app(\App\Domain\Inventory\Instances\Rarity\ItemRarityDropConfigurationService::class));}
    public function generateFor(Monster $monster):LootResult{$entries=MonsterLootEntry::where('monster_id',$monster->id)->where('status',CatalogStatus::ACTIVE)->whereHas('item',function($q){$q->where('status',CatalogStatus::ACTIVE);})->with('item.allowedRarities')->orderBy('sort_order')->orderBy('id')->get();return$this->generateFromLoadedEntries($monster,$entries);}
    public function generateFromLoadedEntries(Monster $monster,Collection $entries):LootResult
    {
        if($monster->status!==CatalogStatus::ACTIVE)throw new InvalidArgumentException('Monster must be active.');
        $items=new \Illuminate\Database\Eloquent\Collection($entries->pluck('item')->filter()->all());$items->loadMissing('allowedRarities');
        $entries=$entries->sortBy(function($e){return sprintf('%010d-%020d',(int)$e->sort_order,(int)$e->id);})->values();$drops=[];
        foreach($entries as $e){if((int)$e->monster_id!==(int)$monster->id||$e->status!==CatalogStatus::ACTIVE||!$e->relationLoaded('item')||!$e->item||$e->item->status!==CatalogStatus::ACTIVE)throw new InvalidArgumentException('Invalid loaded loot catalog.');
            $roll=$this->rng->randomInt(1,1000000);if($roll>$e->drop_probability_ppm)continue;
            $quantity=$e->minimum_quantity===$e->maximum_quantity?$e->minimum_quantity:$this->rng->randomInt($e->minimum_quantity,$e->maximum_quantity);
            $i=$e->item;$kind=$this->classification->classify($i);$rarity=null;
            if($kind===ItemClassification::UNIQUE)$rarity=$this->rarities->roll($i);
            $drops[]=new LootDrop($i->id,$i->code,$i->name,$i->item_type,$quantity,$e->drop_probability_ppm,$roll,$rarity);
        }return new LootResult($monster->id,$monster->code,$monster->name,$drops);
    }
}
