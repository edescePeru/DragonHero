<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class ItemRarityDropSetting extends Model
{
    protected $guarded=[];
    protected $casts=['common_probability_ppm'=>'integer','rare_probability_ppm'=>'integer','mythic_probability_ppm'=>'integer','legendary_probability_ppm'=>'integer','version'=>'integer'];
    public function updatedBy(){return $this->belongsTo(User::class,'updated_by');}
}
