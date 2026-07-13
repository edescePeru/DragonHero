<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;class ZoneEncounterSize extends Model{use HasFactory;protected $guarded=[];protected $casts=['enemy_count'=>'integer','weight'=>'integer','is_active'=>'boolean','sort_order'=>'integer'];public function zone(){return$this->belongsTo(Zone::class);}}
