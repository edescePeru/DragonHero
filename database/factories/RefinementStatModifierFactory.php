<?php
namespace Database\Factories;use App\Models\RefinementStatModifier;use Illuminate\Database\Eloquent\Factories\Factory;
class RefinementStatModifierFactory extends Factory{protected $model=RefinementStatModifier::class;public function definition(){return['refinement_level'=>1,'stat_increase_basis_points'=>1000,'status'=>'active'];}}
