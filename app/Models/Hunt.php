<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class Hunt extends Model {protected $guarded=[];protected $casts=['rounds_count'=>'integer','character_health_before'=>'integer','character_health_after'=>'integer','monster_health_before'=>'integer','monster_health_after'=>'integer','started_at'=>'datetime','completed_at'=>'datetime'];public function character(){return$this->belongsTo(Character::class);}public function zone(){return$this->belongsTo(Zone::class);}public function monster(){return$this->belongsTo(Monster::class);}}
