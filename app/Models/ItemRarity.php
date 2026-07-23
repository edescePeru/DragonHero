<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemRarity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'status', 'sort_order', 'visual_style',
        'weapon_accuracy_bonus_basis_points', 'weapon_critical_bonus_basis_points',
        'armor_evasion_bonus_basis_points', 'armor_speed_bonus_hundredths',
        'armor_absorb_damage_bonus_basis_points', 'border_color_hex',
        'border_opacity_basis_points', 'border_width_px', 'inner_glow_color_hex',
        'inner_glow_opacity_basis_points', 'inner_glow_blur_px', 'inner_glow_spread_px',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'weapon_accuracy_bonus_basis_points' => 'integer',
        'weapon_critical_bonus_basis_points' => 'integer',
        'armor_evasion_bonus_basis_points' => 'integer',
        'armor_speed_bonus_hundredths' => 'integer',
        'armor_absorb_damage_bonus_basis_points' => 'integer',
        'border_opacity_basis_points' => 'integer',
        'border_width_px' => 'integer',
        'inner_glow_opacity_basis_points' => 'integer',
        'inner_glow_blur_px' => 'integer',
        'inner_glow_spread_px' => 'integer',
    ];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_allowed_rarities');
    }

    public function itemInstances()
    {
        return $this->hasMany(ItemInstance::class);
    }
}
