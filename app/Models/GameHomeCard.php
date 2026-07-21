<?php
namespace App\Models;use App\Models\Concerns\HasMediaAssets;use Illuminate\Database\Eloquent\Model;
class GameHomeCard extends Model{use HasMediaAssets;protected $fillable=['name','code','destination_type','destination_value','sort_order','is_active','opens_new_tab'];protected $casts=['sort_order'=>'integer','is_active'=>'boolean','opens_new_tab'=>'boolean'];public function scopeActiveOrdered($query){return$query->where('is_active',true)->orderBy('sort_order')->orderBy('id');}}
