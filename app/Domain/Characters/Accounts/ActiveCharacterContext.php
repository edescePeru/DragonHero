<?php
namespace App\Domain\Characters\Accounts;
use App\Domain\Characters\CharacterStatus;use App\Models\Character;use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;use RuntimeException;
final class ActiveCharacterContext
{
    public function current(User $user){if(!$user->active_character_id)return null;return Character::whereKey($user->active_character_id)->where('user_id',$user->id)->where('status',CharacterStatus::ACTIVE)->first();}
    public function requireCurrent(User $user){$character=$this->current($user);if(!$character)throw new RuntimeException('No hay un personaje activo válido.');return $character;}
    public function select(User $user,Character $character){return DB::transaction(function()use($user,$character){$locked=User::whereKey($user->id)->lockForUpdate()->firstOrFail();$owned=Character::whereKey($character->id)->where('user_id',$locked->id)->where('status',CharacterStatus::ACTIVE)->first();if(!$owned)throw ValidationException::withMessages(['character'=>'El personaje no pertenece a la cuenta o no está activo.']);if((int)$locked->active_character_id!==(int)$owned->id){$locked->active_character_id=$owned->id;$locked->save();}$user->active_character_id=$owned->id;$user->setRelation('activeCharacter',$owned);return $owned;},3);}
    public function clear(User $user){$user->active_character_id=null;$user->save();$user->unsetRelation('activeCharacter');}
}
