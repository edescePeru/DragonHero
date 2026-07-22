<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class CharacterProgressionRevision extends Model
{
    protected $guarded=[];
    protected $casts=['administrator_user_id'=>'integer','previous_max_level'=>'integer','new_max_level'=>'integer','previous_curve'=>'array','new_curve'=>'array'];
    public function administrator(){return $this->belongsTo(User::class,'administrator_user_id');}
}
