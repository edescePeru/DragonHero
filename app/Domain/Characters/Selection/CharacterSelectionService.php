<?php
namespace App\Domain\Characters\Selection;
use App\Domain\Characters\Accounts\CharacterAccountLimit;use App\Domain\Characters\Progression\CharacterProgressionService;use App\Domain\Media\CharacterVisuals\CharacterVisualAssetService;use App\Domain\Media\MediaAssetType;use App\Models\User;
final class CharacterSelectionService
{
 private $progression;private $visuals;public function __construct(CharacterProgressionService $progression,CharacterVisualAssetService $visuals){$this->progression=$progression;$this->visuals=$visuals;}
 public function forUser(User $user){$characters=$user->characters()->with(['characterClass','characterTemplate.mediaAssets'=>function($q){$q->where('asset_type',MediaAssetType::BASE_VISUAL)->where('is_primary',true);}])->orderBy('id')->limit(CharacterAccountLimit::MAX_CHARACTERS)->get();$slots=[];foreach($characters as $character){$slots[]=['character'=>$character,'visual'=>$character->characterTemplate?$this->visuals->presentation($character->characterTemplate):$this->visuals->presentation(),'progress'=>$this->progression->experienceProgress($character),'selected'=>(int)$user->active_character_id===(int)$character->id];}while(count($slots)<CharacterAccountLimit::MAX_CHARACTERS)$slots[]=null;return['slots'=>$slots,'limit'=>CharacterAccountLimit::MAX_CHARACTERS,'atLimit'=>$characters->count()>=CharacterAccountLimit::MAX_CHARACTERS];}
}
