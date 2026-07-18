<?php
namespace Database\Factories;use App\Models\CharacterClass;use Illuminate\Database\Eloquent\Factories\Factory;
class CharacterClassFactory extends Factory{protected $model=CharacterClass::class;public function definition(){return['code'=>$this->faker->unique()->slug,'name'=>$this->faker->unique()->word,'description'=>null,'status'=>'active','sort_order'=>0];}}
