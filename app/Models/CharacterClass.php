<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class CharacterClass extends Model{use HasFactory;protected $guarded=[];protected $casts=['sort_order'=>'integer','can_dual_wield'=>'boolean'];public function characters(){return$this->hasMany(Character::class);}public function items(){return$this->belongsToMany(Item::class,'character_class_item')->withTimestamps();}}
