<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasMediaAssets;

class Character extends Model
{
    use HasFactory, HasMediaAssets;

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
        'base_inventory_slots' => 'integer',
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

    public function wallet(){return $this->hasOne(CharacterWallet::class);}
    public function goldTransactions(){return $this->hasMany(GoldTransaction::class);}
    public function hunts(){return $this->hasMany(Hunt::class);}
    public function huntingSessions(){return $this->hasMany(HuntingSession::class);}
    public function inventoryCapacityGrants(){return $this->hasMany(InventoryCapacityGrant::class);}
}
