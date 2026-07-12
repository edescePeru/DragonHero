<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Region extends Model { protected $guarded=[]; protected $casts=['recommended_level_min'=>'integer','recommended_level_max'=>'integer']; public function world(){return $this->belongsTo(World::class);} public function zones(){return $this->hasMany(Zone::class);} }
