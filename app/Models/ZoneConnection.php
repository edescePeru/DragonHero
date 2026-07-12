<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ZoneConnection extends Model { protected $guarded=[]; protected $casts=['is_bidirectional'=>'boolean','minimum_level'=>'integer']; public function fromZone(){return $this->belongsTo(Zone::class,'from_zone_id');} public function toZone(){return $this->belongsTo(Zone::class,'to_zone_id');} public function requiredItem(){return $this->belongsTo(Item::class,'required_item_id');} }
