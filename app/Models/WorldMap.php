<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class WorldMap extends Model
{
    use HasFactory;
    protected $guarded=[];
    protected $casts=['world_id'=>'integer','region_id'=>'integer','original_width'=>'integer','original_height'=>'integer','file_size'=>'integer','version'=>'integer','is_default'=>'boolean','sort_order'=>'integer'];
    public function world(){return $this->belongsTo(World::class);}public function region(){return $this->belongsTo(Region::class);}public function areas(){return $this->hasMany(WorldMapArea::class);}public function incomingAreas(){return $this->hasMany(WorldMapArea::class,'target_world_map_id');}
}
