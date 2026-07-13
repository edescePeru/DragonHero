<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class InventoryCapacityGrant extends Model{use HasFactory;protected $guarded=[];protected $casts=['slots'=>'integer','is_active'=>'boolean','starts_at'=>'immutable_datetime','ends_at'=>'immutable_datetime','granted_at'=>'immutable_datetime'];public function character(){return $this->belongsTo(Character::class);}}
