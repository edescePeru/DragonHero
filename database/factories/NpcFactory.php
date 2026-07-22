<?php
namespace Database\Factories;use App\Domain\WorldCatalog\CatalogStatus;use App\Models\Npc;use Illuminate\Database\Eloquent\Factories\Factory;
class NpcFactory extends Factory{protected $model=Npc::class;public function definition(){return['code'=>'npc-'.$this->faker->unique()->numerify('########'),'name'=>$this->faker->name,'greeting'=>'Bienvenido, viajero.','status'=>CatalogStatus::ACTIVE];}}
