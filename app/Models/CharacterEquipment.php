<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class CharacterEquipment extends Model{use HasFactory;protected $table='character_equipment';protected $guarded=[];protected $casts=['equipped_at'=>'datetime'];public function character(){return$this->belongsTo(Character::class);}public function itemInstance(){return$this->belongsTo(ItemInstance::class);}}
