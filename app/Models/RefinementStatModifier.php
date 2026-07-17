<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class RefinementStatModifier extends Model{use HasFactory;protected $guarded=[];protected $casts=['refinement_level'=>'integer','stat_increase_basis_points'=>'integer'];}
