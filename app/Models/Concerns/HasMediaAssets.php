<?php
namespace App\Models\Concerns;use App\Models\MediaAsset;
trait HasMediaAssets {public static function bootHasMediaAssets(){static::deleting(function($model){$model->mediaAssets()->delete();});}public function mediaAssets(){return$this->morphMany(MediaAsset::class,'mediable');}public function mediaAssetsOfType($assetType){return$this->mediaAssets()->where('asset_type',$assetType);}public function primaryMediaAsset($assetType){return$this->mediaAssetsOfType($assetType)->where('is_primary',true);}}
