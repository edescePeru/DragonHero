<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class MonsterLootEntry extends Model {protected $guarded=[];protected $casts=['drop_chance_basis_points'=>'integer','drop_probability_ppm'=>'integer','minimum_quantity'=>'integer','maximum_quantity'=>'integer','sort_order'=>'integer'];public function monster(){return$this->belongsTo(Monster::class);}public function item(){return$this->belongsTo(Item::class);}}
