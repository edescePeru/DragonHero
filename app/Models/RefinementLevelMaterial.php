<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefinementLevelMaterial extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = ['quantity' => 'integer'];

    public function rule() { return $this->belongsTo(RefinementLevel::class, 'refinement_level_id'); }
    public function item() { return $this->belongsTo(Item::class); }
}
