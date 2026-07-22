<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class CharacterProgressionSetting extends Model
{
    protected $guarded=[];
    protected $casts=['singleton_key'=>'integer','max_character_level'=>'integer','version'=>'integer','updated_by'=>'integer'];
    public function administrator(){return $this->belongsTo(User::class,'updated_by');}
}
