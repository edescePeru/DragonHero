<?php
namespace App\Domain\Inventory\Instances\Rarity;
use App\Domain\Inventory\Instances\ItemRarityCode;use App\Domain\Random\RandomNumberGenerator;use App\Models\Item;use DomainException;
final class ItemRarityRoller
{
    private $rng,$config;public function __construct(RandomNumberGenerator $rng,ItemRarityDropConfigurationService $config){$this->rng=$rng;$this->config=$config;}
    public function roll(Item $item):ItemRarityRollResult
    {
        if(!$item->relationLoaded('allowedRarities'))throw new DomainException('Item allowed rarities must be loaded.');
        $rank=array_flip(ItemRarityCode::values());$allowed=$item->allowedRarities->filter(function($r)use($rank){return isset($rank[$r->code]);})->sortBy(function($r)use($rank){return$rank[$r->code];})->values();
        if($allowed->isEmpty())throw new DomainException('Unique Item has no allowed rarity.');
        if($allowed->count()===1){$r=$allowed->first();return new ItemRarityRollResult(null,null,(int)$r->id,$r->code,null,null,false,'single_allowed_rarity',true);}
        $config=$this->config->current();$roll=$this->rng->randomInt(1,ItemRarityDropConfiguration::TOTAL_PPM);$cumulative=0;$rolledCode=null;
        foreach(ItemRarityCode::values() as $code){$cumulative+=$config->probability($code);if($roll<=$cumulative){$rolledCode=$code;break;}}
        $rolled=$allowed->firstWhere('code',$rolledCode);if($rolled)return new ItemRarityRollResult((int)$rolled->id,$rolledCode,(int)$rolled->id,$rolledCode,$roll,$config->version(),false,'exact_match',false);
        $rolledRank=$rank[$rolledCode];$lower=$allowed->filter(function($r)use($rank,$rolledRank){return$rank[$r->code]<$rolledRank;})->last();$resolved=$lower?:$allowed->first();$reason=$lower?'nearest_allowed_lower':'minimum_allowed_floor';
        $global=\App\Models\ItemRarity::where('code',$rolledCode)->firstOrFail();
        return new ItemRarityRollResult((int)$global->id,$rolledCode,(int)$resolved->id,$resolved->code,$roll,$config->version(),true,$reason,false);
    }
}
