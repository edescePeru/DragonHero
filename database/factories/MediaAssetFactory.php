<?php

namespace Database\Factories;

use App\Domain\Media\MediaAssetType;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition()
    {
        return [
            'asset_type' => MediaAssetType::IMAGE,
            'disk' => 'public',
            'path' => 'test/example.webp',
            'mime_type' => 'image/webp',
            'width' => 128,
            'height' => 128,
            'file_size' => 4096,
            'metadata' => ['fixture' => true],
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }
}
