<?php
namespace App\Models;use App\Models\Concerns\HasMediaAssets;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class Npc extends Model{use HasFactory,HasMediaAssets;protected $guarded=[];public function shops(){return$this->hasMany(Shop::class);}}
