<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoldTransaction extends Model { protected $guarded=[]; protected $casts=['amount'=>'integer','balance_before'=>'integer','balance_after'=>'integer','reference_id'=>'integer']; public function character(){return $this->belongsTo(Character::class);} }
