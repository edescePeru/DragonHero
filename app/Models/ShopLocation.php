<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class ShopLocation extends Model{use HasFactory;protected $guarded=[];protected $casts=['sort_order'=>'integer'];public function shop(){return$this->belongsTo(Shop::class);}public function locatable(){return$this->morphTo();}}
