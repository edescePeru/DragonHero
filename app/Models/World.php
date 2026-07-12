<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class World extends Model { protected $guarded=[]; public function regions(){return $this->hasMany(Region::class);} }
