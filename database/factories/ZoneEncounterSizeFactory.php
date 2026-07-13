<?php
namespace Database\Factories;use App\Models\Zone;use App\Models\ZoneEncounterSize;use Illuminate\Database\Eloquent\Factories\Factory;class ZoneEncounterSizeFactory extends Factory{protected $model=ZoneEncounterSize::class;public function definition(){return['zone_id'=>Zone::query()->value('id'),'enemy_count'=>1,'weight'=>100,'is_active'=>true,'sort_order'=>1];}}
