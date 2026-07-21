<?php

namespace App\Policies;

use App\Domain\Characters\Accounts\CharacterAccountLimit;
use App\Models\Character;
use App\Models\User;

class CharacterPolicy
{
    public function create(User $user)
    {
        return $user->characters()->count() < CharacterAccountLimit::MAX_CHARACTERS;
    }

    public function view(User $user, Character $character)
    {
        return (int) $user->getKey() === (int) $character->user_id;
    }
}
