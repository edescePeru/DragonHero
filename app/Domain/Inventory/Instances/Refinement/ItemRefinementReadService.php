<?php

namespace App\Domain\Inventory\Instances\Refinement;

use App\Domain\Equipment\EquippableItemValidator;
use App\Domain\Inventory\Instances\ItemInstanceLimits;
use App\Domain\Wallet\WalletService;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\ItemInstance;
use App\Models\RefinementLevel;
use App\Models\RefinementStatModifier;
use InvalidArgumentException;

final class ItemRefinementReadService
{
    private $tokens;private $rules;private $equippable;private $wallet;private $statCalculator;
    public function __construct(RefinementTokenService $tokens,RefinementRuleValidator $rules,EquippableItemValidator $equippable,WalletService $wallet,ItemRefinementStatCalculator $statCalculator){$this->tokens=$tokens;$this->rules=$rules;$this->equippable=$equippable;$this->wallet=$wallet;$this->statCalculator=$statCalculator;}
    public function previews(Character $character)
    {
        $instances=ItemInstance::where('character_id',$character->id)->with('item')->orderBy('id')->get();
        $levels=$instances->pluck('refinement_level')->unique();$rules=RefinementLevel::whereIn('from_level',$levels)->with(['materials.item'])->get()->keyBy('from_level');$statModifiers=RefinementStatModifier::whereIn('refinement_level',$levels)->where('status',CatalogStatus::ACTIVE)->get()->keyBy('refinement_level');
        $materialIds=$rules->flatMap(function($r){return$r->materials->pluck('item_id');})->unique();$owned=CharacterItem::where('character_id',$character->id)->whereIn('item_id',$materialIds)->get()->keyBy('item_id');$gold=$this->wallet->balance($character)->balance();$previews=[];
        foreach($instances as $instance){$item=$instance->item;$rule=$rules->get($instance->refinement_level);$available=true;$reason=null;$materials=[];
            try{if(!$item||$item->status!==CatalogStatus::ACTIVE||$this->equippable->equipmentType($item)===null)throw new InvalidArgumentException('Objeto no refinable.');if((int)$instance->refinement_level>=ItemInstanceLimits::MAX_REFINEMENT_LEVEL)throw new InvalidArgumentException('Nivel máximo alcanzado.');if(!$rule||$rule->status!==CatalogStatus::ACTIVE)throw new InvalidArgumentException('Regla no disponible.');$this->rules->validate($rule);foreach($rule->materials->sortBy('item_id') as $line){$this->rules->validateMaterial($line->item,$line->quantity);$row=$owned->get($line->item_id);$have=$row?(int)$row->quantity-(int)$row->locked_quantity:0;$materials[]=['item_name'=>$line->item->name,'required'=>(int)$line->quantity,'available'=>$have];if($have<(int)$line->quantity){$available=false;$reason='Materiales insuficientes.';}}if($gold<(int)$rule->gold_cost){$available=false;$reason='Oro insuficiente.';}}catch(InvalidArgumentException $e){$available=false;$reason=$e->getMessage();}
            $stats=$item?$this->statCalculator->calculate(['max_health'=>$item->max_health_bonus,'attack'=>$item->attack_bonus,'defense'=>$item->defense_bonus,'accuracy'=>$item->accuracy_bonus,'evasion'=>$item->evasion_bonus,'critical_chance'=>$item->critical_chance_bonus,'attack_speed'=>$item->attack_speed_bonus],(int)$instance->refinement_level,$statModifiers->get((int)$instance->refinement_level)):null;
            $previews[$instance->uuid]=(new RefinementPreview(['uuid'=>$instance->uuid,'item_name'=>$item?$item->name:'Objeto desconocido','status'=>$instance->status,'current_level'=>(int)$instance->refinement_level,'next_level'=>$rule?(int)$rule->to_level:null,'gold_cost'=>$rule?(int)$rule->gold_cost:0,'gold_available'=>$gold,'materials'=>$materials,'can_refine'=>$available,'unavailable_reason'=>$reason,'token'=>$available?$this->tokens->issue($character,$instance):null,'stat_modifier_configured'=>$stats?$stats->configured():false,'stat_modifier_basis_points'=>$stats?$stats->basisPoints():null,'base_bonuses'=>$stats?$stats->base()->toArray():[],'refinement_bonuses'=>$stats?$stats->refinement()->toArray():[],'total_bonuses'=>$stats?$stats->total()->toArray():[]]))->toArray();
        }return$previews;
    }
}
