<?php
namespace App\Models;
use App\Models\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Model;
class Region extends Model { use HasMediaAssets; protected $guarded=[]; protected $casts=['recommended_level_min'=>'integer','recommended_level_max'=>'integer']; public function world(){return $this->belongsTo(World::class);} public function zones(){return $this->hasMany(Zone::class);} public function worldMaps(){return$this->hasMany(WorldMap::class);} }
