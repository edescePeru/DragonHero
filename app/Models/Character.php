<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $casts = [
        'level' => 'integer',
        'experience' => 'integer',
        'current_health' => 'integer',
        'base_max_health' => 'integer',
        'base_attack' => 'integer',
        'base_defense' => 'integer',
        'base_accuracy' => 'integer',
        'base_evasion' => 'integer',
        'base_critical_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function characterItems()
    {
        return $this->hasMany(CharacterItem::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'character_items')
            ->withPivot(['id', 'quantity', 'locked_quantity'])
            ->withTimestamps();
    }
}
