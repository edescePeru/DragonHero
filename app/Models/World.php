<?php
namespace App\Models;
use App\Models\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Model;
class World extends Model { use HasMediaAssets; protected $guarded=[]; public function regions(){return $this->hasMany(Region::class);} public function worldMaps(){return$this->hasMany(WorldMap::class);} }
