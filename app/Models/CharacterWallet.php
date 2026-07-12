<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CharacterWallet extends Model { protected $guarded=['gold_balance']; protected $casts=['gold_balance'=>'integer']; public function character(){return $this->belongsTo(Character::class);} }
