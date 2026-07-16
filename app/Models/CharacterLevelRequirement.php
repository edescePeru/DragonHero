<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterLevelRequirement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'level' => 'integer',
        'required_experience' => 'integer',
    ];
}
