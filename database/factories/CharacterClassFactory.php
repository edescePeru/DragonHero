<?php
namespace Database\Factories;use App\Models\CharacterClass;use Illuminate\Database\Eloquent\Factories\Factory;
class CharacterClassFactory extends Factory{protected $model=CharacterClass::class;public function definition(){return['code'=>substr($this->faker->unique()->slug,0,64),'name'=>$this->faker->unique()->word,'description'=>null,'status'=>'active','can_dual_wield'=>false,'sort_order'=>0];}}
