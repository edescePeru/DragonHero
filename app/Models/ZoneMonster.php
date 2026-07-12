<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;
class ZoneMonster extends Pivot { protected $table='zone_monsters'; public $incrementing=true; protected $guarded=[]; protected $casts=['weight'=>'integer','minimum_character_level'=>'integer','maximum_character_level'=>'integer']; public function zone(){return $this->belongsTo(Zone::class);} public function monster(){return $this->belongsTo(Monster::class);} }
