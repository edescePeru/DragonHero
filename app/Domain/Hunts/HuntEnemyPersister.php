<?php
namespace App\Domain\Hunts;use App\Models\Hunt;use App\Models\HuntEnemy;class HuntEnemyPersister{public function create(Hunt $hunt,array $attributes){return HuntEnemy::create(array_merge(['hunt_id'=>$hunt->id],$attributes));}}
