<?php
namespace App\Models;
use App\Models\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Model;
class Item extends Model{use HasMediaAssets;protected $guarded=[];protected $casts=['is_stackable'=>'boolean','max_stack'=>'integer','required_level'=>'integer','max_health_bonus'=>'integer','attack_bonus'=>'integer','defense_bonus'=>'integer','accuracy_bonus'=>'integer','evasion_bonus'=>'integer','critical_chance_bonus'=>'decimal:2','attack_speed_bonus'=>'decimal:2'];public function monsterLootEntries(){return$this->hasMany(MonsterLootEntry::class);}public function requiredByConnections(){return$this->hasMany(ZoneConnection::class,'required_item_id');}public function characterItems(){return$this->hasMany(CharacterItem::class);}public function instances(){return$this->hasMany(ItemInstance::class);}public function allowedCharacterClasses(){return$this->belongsToMany(CharacterClass::class,'character_class_item')->withTimestamps();}}
