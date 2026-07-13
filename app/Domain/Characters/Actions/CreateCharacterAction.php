<?php

namespace App\Domain\Characters\Actions;

use App\Domain\Characters\CharacterStatus;
use App\Domain\Inventory\Capacity\InventoryCapacityLimits;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCharacterAction
{
    public function execute(User $user, $name)
    {
        try {
            return DB::transaction(function () use ($user, $name) {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->characters()->exists()) {
                    throw ValidationException::withMessages([
                        'name' => 'Solo puedes crear un personaje en esta fase.',
                    ]);
                }

                $character = new Character();
                $character->name = $name;
                $character->level = 1;
                $character->experience = 0;
                $character->current_health = 100;
                $character->base_max_health = 100;
                $character->base_attack = 10;
                $character->base_defense = 5;
                $character->base_accuracy = 80;
                $character->base_evasion = 5;
                $character->base_critical_rate = '5.00';
                $character->status = CharacterStatus::ACTIVE;
                $character->base_inventory_slots = InventoryCapacityLimits::DEFAULT_BASE_SLOTS;

                $lockedUser->characters()->save($character);

                return $character;
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'name' => 'El nombre del personaje ya está en uso.',
                ]);
            }

            throw $exception;
        }
    }
}
