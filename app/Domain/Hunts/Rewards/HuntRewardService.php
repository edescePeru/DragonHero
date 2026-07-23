<?php
namespace App\Domain\Hunts\Rewards;
use App\Domain\Combat\CombatResultStatus;use App\Domain\Hunts\HuntEnemyStatus;use App\Domain\Hunts\Rewards\Data\HuntRewardItemResult;use App\Domain\Hunts\Rewards\Data\HuntRewardResult;use App\Domain\Hunts\Rewards\Exceptions\InconsistentHuntRewardException;use App\Domain\Inventory\ItemClassification;use App\Domain\Loot\LootGenerator;use App\Domain\WorldCatalog\CatalogStatus;use App\Models\Hunt;use App\Models\HuntEnemy;use App\Models\HuntReward;use App\Models\HuntRewardItem;use App\Models\Item;use App\Models\ItemRarity;use App\Models\Monster;use App\Models\MonsterLootEntry;use Illuminate\Support\Facades\DB;use InvalidArgumentException;
final class HuntRewardService
{
    private $loot,$values,$classification;public function __construct(LootGenerator $loot,HuntRewardValueGenerator $values,ItemClassification $classification){$this->loot=$loot;$this->values=$values;$this->classification=$classification;}
    public function generatePendingForLockedHunt($huntId,$sessionId):HuntRewardResult
    {
        if(DB::transactionLevel()<1)throw new \RuntimeException('Active transaction required.');
        $hunt=Hunt::whereKey($huntId)->lockForUpdate()->firstOrFail();if((int)$hunt->hunting_session_id!==(int)$sessionId||$hunt->status!==CombatResultStatus::CHARACTER_VICTORY)throw new InconsistentHuntRewardException('Hunt is not rewardable.');
        $existing=HuntReward::where('hunt_id',$hunt->id)->lockForUpdate()->first();if($existing)return$this->result($existing->load('items'));
        $enemies=HuntEnemy::where('hunt_id',$hunt->id)->orderBy('position')->lockForUpdate()->get();$this->validateEnemies($hunt,$enemies);
        $monsterIds=$enemies->pluck('monster_id')->unique()->values();$monsters=Monster::whereIn('id',$monsterIds)->lockForUpdate()->get()->keyBy('id');if($monsters->count()!==$monsterIds->count())throw new InconsistentHuntRewardException('Monster catalog missing.');
        $values=$this->values->generate($enemies,$monsters);$reward=HuntReward::create(['hunt_id'=>$hunt->id,'hunting_session_id'=>$hunt->hunting_session_id,'status'=>HuntRewardStatus::PENDING,'gold_amount'=>$values->gold(),'experience_amount'=>$values->experience(),'generated_at'=>now(),'claimed_at'=>null]);
        $entries=MonsterLootEntry::whereIn('monster_id',$monsterIds)->where('status',CatalogStatus::ACTIVE)->whereHas('item',function($q){$q->where('status',CatalogStatus::ACTIVE);})->with('item.allowedRarities')->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->groupBy('monster_id');
        foreach($enemies as $enemy){$loot=$this->loot->generateFromLoadedEntries($monsters->get($enemy->monster_id),$entries->get($enemy->monster_id,collect()));foreach($loot->drops() as $drop){$quantity=$drop->quantity();if(!is_int($quantity)||$quantity<=0)throw new InvalidArgumentException('Invalid reward quantity.');$rarity=$drop->itemRarityRollResult();$rarityModel=$rarity?ItemRarity::whereKey($rarity->resolvedRarityId())->firstOrFail():null;HuntRewardItem::create(['hunt_reward_id'=>$reward->id,'hunt_enemy_id'=>$enemy->id,'item_id'=>$drop->itemId(),'source_instance_identifier'=>$enemy->instance_identifier,'item_code_snapshot'=>$drop->itemCode(),'item_name_snapshot'=>$drop->itemName(),'quantity'=>$quantity,'item_rarity_id'=>$rarityModel?$rarityModel->id:null,'rarity_code_snapshot'=>$rarityModel?$rarityModel->code:null,'rarity_name_snapshot'=>$rarityModel?$rarityModel->name:null,'rarity_roll_metadata'=>$rarity?$rarity->metadata():null]);}}
        return$this->result($reward->load('items'));
    }
    private function validateEnemies(Hunt $hunt,$enemies){if($enemies->count()!==$hunt->enemy_count||$enemies->pluck('position')->all()!==range(1,$hunt->enemy_count)||$enemies->pluck('instance_identifier')->filter()->unique()->count()!==$hunt->enemy_count)throw new InconsistentHuntRewardException('Enemy encounter mismatch.');foreach($enemies as $enemy)if($enemy->status!==HuntEnemyStatus::DEFEATED||(int)$enemy->final_health!==0)throw new InconsistentHuntRewardException('Victory enemy is not defeated.');}
    private function result(HuntReward $reward){$items=$reward->items->map(function($i){return new HuntRewardItemResult($i->id,$i->hunt_enemy_id,$i->source_instance_identifier,$i->item_id,$i->item_code_snapshot,$i->item_name_snapshot,$i->quantity);})->all();return new HuntRewardResult($reward->id,$reward->hunt_id,$reward->hunting_session_id,$reward->status,$reward->generated_at->toIso8601String(),(int)$reward->gold_amount,(int)$reward->experience_amount,$items);}
    public function summary($sessionId){return$this->summaryQuery(HuntReward::where('hunting_session_id',$sessionId),function($q)use($sessionId){$q->where('r.hunting_session_id',$sessionId);});}
    public function summaryPendingForCharacter($character){return$this->summaryQuery(HuntReward::query()->join('hunting_sessions','hunting_sessions.id','=','hunt_rewards.hunting_session_id')->where('hunting_sessions.character_id',$character->id)->select('hunt_rewards.*'),function($q)use($character){$q->join('hunting_sessions as s','s.id','=','r.hunting_session_id')->where('s.character_id',$character->id);});}
    private function summaryQuery($base,$scope)
    {
        $base->where('hunt_rewards.status',HuntRewardStatus::PENDING);
        $rewards=(clone$base)->count();$withItems=(clone$base)->whereHas('items')->count();
        $gold=(int)(clone$base)->sum('hunt_rewards.gold_amount');$experience=(int)(clone$base)->sum('hunt_rewards.experience_amount');
        $itemsBase=DB::table('hunt_reward_items as i')->join('hunt_rewards as r','r.id','=','i.hunt_reward_id')->where('r.status',HuntRewardStatus::PENDING);$scope($itemsBase);
        $raw=(clone$itemsBase)->select('i.item_id','i.item_rarity_id','i.rarity_code_snapshot','i.rarity_name_snapshot',DB::raw('MAX(i.item_code_snapshot) item_code_snapshot'),DB::raw('MAX(i.item_name_snapshot) item_name_snapshot'),DB::raw('SUM(i.quantity) quantity'))->groupBy('i.item_id','i.item_rarity_id','i.rarity_code_snapshot','i.rarity_name_snapshot')->orderBy('i.item_id')->orderBy('i.item_rarity_id')->get();
        $items=$raw->isEmpty()?collect():Item::whereIn('id',$raw->pluck('item_id')->unique())->get()->keyBy('id');
        $rows=[];
        foreach($raw as $row){
            $item=$items->get($row->item_id);if(!$item)throw new InvalidArgumentException('Reward Item is missing.');
            $classification=$this->classification->classify($item);$rarityId=$classification===ItemClassification::UNIQUE&&$row->item_rarity_id!==null?(int)$row->item_rarity_id:null;
            $key=$classification===ItemClassification::STACKABLE?'stackable:'.$row->item_id:'unique:'.$row->item_id.':'.($rarityId===null?'legacy':$rarityId);
            if(!isset($rows[$key]))$rows[$key]=['item_id'=>(int)$row->item_id,'item_code'=>$row->item_code_snapshot,'item_name'=>$row->item_name_snapshot,'quantity'=>0,'classification'=>$classification,'is_unique'=>$classification===ItemClassification::UNIQUE,'item_rarity_id'=>$rarityId,'rarity_code'=>$rarityId!==null?$row->rarity_code_snapshot:null,'rarity_name'=>$rarityId!==null?$row->rarity_name_snapshot:null];
            $rows[$key]['quantity']+=(int)$row->quantity;
        }
        $rows=array_values($rows);
        return['rewards_count'=>$rewards,'rewards_with_items_count'=>$withItems,'item_lines_count'=>(int)(clone$itemsBase)->count(),'total_quantity'=>(int)collect($rows)->sum('quantity'),'gold_amount'=>$gold,'experience_amount'=>$experience,'items'=>$rows];
    }
}
