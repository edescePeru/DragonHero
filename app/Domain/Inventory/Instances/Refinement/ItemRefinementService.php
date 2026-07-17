<?php

namespace App\Domain\Inventory\Instances\Refinement;

use App\Domain\Equipment\EquippableItemValidator;
use App\Domain\Inventory\Instances\ItemInstanceEventService;
use App\Domain\Inventory\Instances\ItemInstanceEventType;
use App\Domain\Inventory\Instances\ItemInstanceLimits;
use App\Domain\Inventory\Instances\ItemInstanceStatus;
use App\Domain\Inventory\InventoryService;
use App\Domain\Random\RandomNumberGenerator;
use App\Domain\Wallet\GoldReasonCode;
use App\Domain\Wallet\WalletService;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterWallet;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\ItemInstanceEvent;
use App\Models\RefinementLevel;
use App\Models\RefinementLevelMaterial;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ItemRefinementService
{
    private $tokens; private $rules; private $equippable; private $inventory; private $wallet; private $events; private $rng;
    public function __construct(RefinementTokenService $tokens, RefinementRuleValidator $rules, EquippableItemValidator $equippable, InventoryService $inventory, WalletService $wallet, ItemInstanceEventService $events, RandomNumberGenerator $rng) { $this->tokens=$tokens;$this->rules=$rules;$this->equippable=$equippable;$this->inventory=$inventory;$this->wallet=$wallet;$this->events=$events;$this->rng=$rng; }

    public function refine(Character $character, ItemInstance $instance, $opaqueToken)
    {
        $token = $this->tokens->decode($opaqueToken);
        if ((int)$token['character_id'] !== (int)$character->id || $token['item_instance_uuid'] !== $instance->uuid) throw new InvalidArgumentException('Refinement token context mismatch.');
        return DB::transaction(function () use ($character, $instance, $token) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $lockedInstance = ItemInstance::whereKey($instance->id)->lockForUpdate()->firstOrFail();
            if ((int)$lockedInstance->character_id !== (int)$lockedCharacter->id || $lockedInstance->uuid !== $token['item_instance_uuid']) throw new InvalidArgumentException('ItemInstance does not belong to this Character.');

            $existing = ItemInstanceEvent::where('item_instance_id',$lockedInstance->id)->where('event_type',ItemInstanceEventType::REFINEMENT_SUCCEEDED)->where('operation_uuid',$token['operation_uuid'])->lockForUpdate()->first();
            if ($existing) return new RefinementResult($existing->metadata);
            if ((int)$token['observed_refinement_level'] !== (int)$lockedInstance->refinement_level) throw new InvalidArgumentException('Refinement operation is stale.');
            if (!in_array($lockedInstance->status,[ItemInstanceStatus::AVAILABLE,ItemInstanceStatus::EQUIPPED],true)) throw new InvalidArgumentException('ItemInstance status cannot be refined.');
            if ((int)$lockedInstance->refinement_level >= ItemInstanceLimits::MAX_REFINEMENT_LEVEL) throw new InvalidArgumentException('ItemInstance already reached maximum refinement.');

            $item = Item::whereKey($lockedInstance->item_id)->lockForUpdate()->firstOrFail();
            if ($item->status !== CatalogStatus::ACTIVE || $this->equippable->equipmentType($item) === null) throw new InvalidArgumentException('Only active valid equipment can be refined.');
            $rule = RefinementLevel::where('from_level',$lockedInstance->refinement_level)->where('status',CatalogStatus::ACTIVE)->lockForUpdate()->first();
            if (!$rule) throw new InvalidArgumentException('No active refinement rule is configured.');
            $this->rules->validate($rule);
            $materialRules = RefinementLevelMaterial::where('refinement_level_id',$rule->id)->orderBy('item_id')->lockForUpdate()->get();
            $materialIds = $materialRules->pluck('item_id')->map(function($id){return(int)$id;})->all();
            $materials = empty($materialIds)?collect():Item::whereIn('id',$materialIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $quantities=[]; foreach($materialRules as $materialRule){$material=$materials->get($materialRule->item_id);if(!$material)throw new RuntimeException('Refinement material is missing.');$this->rules->validateMaterial($material,$materialRule->quantity);$quantities[(int)$material->id]=(int)$materialRule->quantity;}

            $lockedWallet=CharacterWallet::where('character_id',$lockedCharacter->id)->lockForUpdate()->first();
            if(!$lockedWallet){$lockedWallet=new CharacterWallet();$lockedWallet->character_id=$lockedCharacter->id;$lockedWallet->gold_balance=0;$lockedWallet->save();}
            $inventoryRows=empty($materialIds)?collect():CharacterItem::where('character_id',$lockedCharacter->id)->whereIn('item_id',$materialIds)->orderBy('item_id')->lockForUpdate()->get();
            foreach($quantities as $materialId=>$quantity){$row=$inventoryRows->firstWhere('item_id',$materialId);if(!$row||(int)$row->quantity-(int)$row->locked_quantity<$quantity)throw new InvalidArgumentException('Insufficient refinement materials.');}
            $gold=(int)$rule->gold_cost;
            if($gold>0)$this->wallet->debitLocked($lockedCharacter,$gold,GoldReasonCode::ITEM_REFINEMENT,'Refinement '.$token['operation_uuid'].' +'.$rule->from_level.' to +'.$rule->to_level.' rule '.$rule->id,'item_instance',(int)$lockedInstance->id,$token['operation_uuid'],$lockedWallet);
            if(!empty($quantities))$this->inventory->withdrawMultipleLocked($lockedCharacter,$materials,$quantities,$inventoryRows);
            $roll=$this->rng->randomInt(1,10000);
            if($roll>(int)$rule->success_chance_basis_points)throw new RuntimeException('Unexpected refinement failure in v1.');
            $from=(int)$lockedInstance->refinement_level;$lockedInstance->refinement_level=(int)$rule->to_level;$lockedInstance->save();
            $now=CarbonImmutable::now();$consumed=[];foreach($quantities as $materialId=>$quantity){$material=$materials->get($materialId);$consumed[]=['item_id'=>$materialId,'item_code'=>$material->code,'item_name'=>$material->name,'quantity'=>$quantity];}
            $payload=['schema_version'=>1,'operation_uuid'=>$token['operation_uuid'],'character_id'=>(int)$lockedCharacter->id,'item_instance_id'=>(int)$lockedInstance->id,'item_instance_uuid'=>$lockedInstance->uuid,'item_id'=>(int)$item->id,'item_code'=>$item->code,'item_name'=>$item->name,'from_level'=>$from,'to_level'=>(int)$rule->to_level,'current_level'=>(int)$lockedInstance->refinement_level,'refinement_rule_id'=>(int)$rule->id,'success_chance_basis_points'=>(int)$rule->success_chance_basis_points,'roll'=>$roll,'gold_consumed'=>$gold,'materials_consumed'=>$consumed,'instance_status'=>$lockedInstance->status,'attempted_at'=>$now->toIso8601String()];
            $this->events->appendRefinementSucceeded($lockedInstance,$lockedCharacter,$item,$token['operation_uuid'],$payload,$now);
            return new RefinementResult($payload);
        },3);
    }
}
