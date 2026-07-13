<?php
namespace App\Models;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Facades\Storage;
class MediaAsset extends Model {use HasFactory;protected $guarded=[];protected $casts=['metadata'=>'array','is_primary'=>'boolean','width'=>'integer','height'=>'integer','file_size'=>'integer','sort_order'=>'integer'];public function mediable(){return$this->morphTo();}public function url(){return Storage::disk($this->disk)->url($this->path);}}
