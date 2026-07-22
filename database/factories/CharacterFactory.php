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
            'character_class_id' => function(){return CharacterClass::where('status','active')->orderBy('sort_order')->orderBy('id')->value('id') ?: CharacterClass::factory()->create()->id;},
            'name' => $this->faker->unique()->userName,
            'normalized_name' => function (array $attributes) {
                return mb_strtolower(preg_replace('/\s+/u', ' ', trim($attributes['name'])), 'UTF-8');
            },
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

    public function fromTemplate(\App\Models\CharacterTemplate $template)
    {
        return $this->state(function () use ($template) {
            return [
                'character_template_id' => $template->id,
                'character_class_id' => $template->character_class_id,
                'current_health' => $template->base_max_health,
                'base_max_health' => $template->base_max_health,
                'base_attack' => $template->base_attack,
                'base_defense' => $template->base_defense,
                'base_accuracy' => $template->base_accuracy,
                'base_evasion' => $template->base_evasion,
                'base_critical_rate' => $template->base_critical_rate,
            ];
        });
    }

    public function selectedFor(User $user)
    {
        return $this->state(['user_id' => $user->id])->afterCreating(function (Character $character) use ($user) {
            $user->forceFill(['active_character_id' => $character->id])->save();
        });
    }

    public function selected()
    {
        return $this->afterCreating(function (Character $character) {
            $character->user->forceFill(['active_character_id' => $character->id])->save();
        });
    }
}
