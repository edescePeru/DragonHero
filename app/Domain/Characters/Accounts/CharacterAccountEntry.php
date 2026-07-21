<?php
namespace App\Domain\Characters\Accounts;
use App\Models\User;
final class CharacterAccountEntry{public function route(User $user){return $user->characters()->exists()?'characters.select':'characters.create';}}
