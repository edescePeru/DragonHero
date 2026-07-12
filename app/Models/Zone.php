<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Zone extends Model { protected $guarded=[]; protected $casts=['recommended_level_min'=>'integer','recommended_level_max'=>'integer','is_safe'=>'boolean','allows_hunting'=>'boolean']; public function region(){return $this->belongsTo(Region::class);} public function monsters(){return $this->belongsToMany(Monster::class,'zone_monsters')->using(ZoneMonster::class)->withPivot(['id','weight','minimum_character_level','maximum_character_level','status'])->withTimestamps();} public function outgoingConnections(){return $this->hasMany(ZoneConnection::class,'from_zone_id');} public function incomingConnections(){return $this->hasMany(ZoneConnection::class,'to_zone_id');} }
