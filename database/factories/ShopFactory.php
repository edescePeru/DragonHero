<?php
namespace Database\Factories;use App\Domain\WorldCatalog\CatalogStatus;use App\Models\Npc;use App\Models\Shop;use Illuminate\Database\Eloquent\Factories\Factory;
class ShopFactory extends Factory{protected $model=Shop::class;public function definition(){return['code'=>'shop-'.$this->faker->unique()->numerify('########'),'npc_id'=>Npc::factory(),'name'=>$this->faker->company.' Shop','description'=>null,'status'=>CatalogStatus::ACTIVE,'starts_at'=>null,'ends_at'=>null,'sort_order'=>0];}}
