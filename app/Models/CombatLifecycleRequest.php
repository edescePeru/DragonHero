<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CombatLifecycleRequest extends Model
{
    protected $guarded=[];
    protected $casts=['expected_lock_version'=>'integer','lock_version_before'=>'integer','lock_version_after'=>'integer','response_payload'=>'array','processed_at'=>'datetime'];
    public function combatSession(){return $this->belongsTo(CombatSession::class);}
}
