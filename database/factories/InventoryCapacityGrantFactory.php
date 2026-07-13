<?php
namespace Database\Factories;
use App\Models\Character;
use App\Models\InventoryCapacityGrant;
use Illuminate\Database\Eloquent\Factories\Factory;
class InventoryCapacityGrantFactory extends Factory{protected $model=InventoryCapacityGrant::class;public function definition(){return['character_id'=>Character::factory(),'slots'=>10,'source_type'=>'test','source_identifier'=>$this->faker->unique()->uuid,'is_active'=>true,'starts_at'=>null,'ends_at'=>null,'granted_at'=>now()];}}
