<?php
namespace App\Models;
use App\Models\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Model;
class Item extends Model { use HasMediaAssets; protected $guarded=[]; protected $casts=['is_stackable'=>'boolean','max_stack'=>'integer']; public function monsterLootEntries(){return $this->hasMany(MonsterLootEntry::class);} public function requiredByConnections(){return $this->hasMany(ZoneConnection::class,'required_item_id');} public function characterItems(){return $this->hasMany(CharacterItem::class);} }
