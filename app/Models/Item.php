<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Item extends Model { protected $guarded=[]; protected $casts=['is_stackable'=>'boolean','max_stack'=>'integer']; public function requiredByConnections(){return $this->hasMany(ZoneConnection::class,'required_item_id');} }
