<?php

namespace Database\Factories;

use App\Domain\Characters\CharacterStatus;
use App\Domain\Inventory\Capacity\InventoryCapacityLimits;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CharacterFactory extends Factory
{
    protected $model = Character::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'character_class_id' => function(){return CharacterClass::firstOrCreate(['code'=>'adventurer'],['name'=>'Aventurero','description'=>'Clase neutral inicial.','status'=>'active','sort_order'=>0])->id;},
            'name' => $this->faker->unique()->userName,
            'level' => 1,
            'experience' => 0,
            'current_health' => 100,
            'base_max_health' => 100,
            'base_attack' => 10,
            'base_defense' => 5,
            'base_accuracy' => 80,
            'base_evasion' => 5,
            'base_critical_rate' => '5.00',
            'status' => CharacterStatus::ACTIVE,
            'base_inventory_slots' => InventoryCapacityLimits::DEFAULT_BASE_SLOTS,
        ];
    }
}
