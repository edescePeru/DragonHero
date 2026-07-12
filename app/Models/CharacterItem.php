<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CharacterItem extends Model {
 protected $guarded=[];
 protected $casts=['quantity'=>'integer','locked_quantity'=>'integer'];
 public function character(){return $this->belongsTo(Character::class);}
 public function item(){return $this->belongsTo(Item::class);}
}
