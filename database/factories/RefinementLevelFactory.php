<?php
namespace Database\Factories;use App\Models\RefinementLevel;use Illuminate\Database\Eloquent\Factories\Factory;
class RefinementLevelFactory extends Factory {protected $model=RefinementLevel::class;public function definition(){return['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>10000,'gold_cost'=>10,'failure_behavior'=>'keep_level','status'=>'active'];}}
