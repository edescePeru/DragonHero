<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class ItemVisualAsset extends Model{protected $guarded=[];public function item(){return$this->belongsTo(Item::class);}public function mediaAsset(){return$this->belongsTo(MediaAsset::class);}}
