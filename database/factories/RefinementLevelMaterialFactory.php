<?php
namespace Database\Factories;use App\Models\Item;use App\Models\RefinementLevel;use App\Models\RefinementLevelMaterial;use Illuminate\Database\Eloquent\Factories\Factory;
class RefinementLevelMaterialFactory extends Factory {protected $model=RefinementLevelMaterial::class;public function definition(){return['refinement_level_id'=>RefinementLevel::factory(),'item_id'=>function(){return Item::create(['code'=>$this->faker->unique()->slug,'name'=>$this->faker->word,'item_type'=>'material','rarity'=>'common','is_stackable'=>true,'max_stack'=>99,'status'=>'active'])->id;},'quantity'=>2];}}
