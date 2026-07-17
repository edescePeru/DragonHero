<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefinementLevel extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = ['from_level' => 'integer', 'to_level' => 'integer', 'success_chance_basis_points' => 'integer', 'gold_cost' => 'integer'];

    public function materials()
    {
        return $this->hasMany(RefinementLevelMaterial::class);
    }
}
