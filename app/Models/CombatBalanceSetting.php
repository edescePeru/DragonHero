<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
final class CombatBalanceSetting extends Model{protected $guarded=[];protected $casts=['defense_reduction_cap_basis_points'=>'integer','absorb_damage_cap_basis_points'=>'integer','total_mitigation_cap_basis_points'=>'integer','minimum_damage'=>'integer','version'=>'integer'];public function administrator(){return$this->belongsTo(User::class,'updated_by');}}
