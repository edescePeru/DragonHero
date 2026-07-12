<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Monster extends Model { protected $guarded=[]; protected $casts=['level'=>'integer','max_health'=>'integer','attack'=>'integer','defense'=>'integer','accuracy_rate'=>'decimal:2','evasion_rate'=>'decimal:2','critical_chance'=>'decimal:2','experience_reward'=>'integer']; public function zones(){return $this->belongsToMany(Zone::class,'zone_monsters')->using(ZoneMonster::class)->withPivot(['id','weight','minimum_character_level','maximum_character_level','status'])->withTimestamps();} }
