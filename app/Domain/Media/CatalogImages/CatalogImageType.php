<?php

namespace App\Domain\Media\CatalogImages;

use App\Domain\Media\MediaAssetType;
use App\Models\Item;
use App\Models\Monster;
use App\Models\World;
use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class CatalogImageType
{
    const ITEM = 'item';
    const MONSTER = 'monster';
    const WORLD = 'world';
    const CHARACTER_CLASS = 'character_class';

    public static function values()
    {
        return [self::ITEM, self::MONSTER, self::WORLD, self::CHARACTER_CLASS];
    }

    public static function assetType($type)
    {
        if ($type === self::ITEM) return MediaAssetType::ICON;
        if ($type === self::MONSTER) return MediaAssetType::PORTRAIT;
        if ($type === self::WORLD) return MediaAssetType::IMAGE;
        if ($type === self::CHARACTER_CLASS) return MediaAssetType::ICON;

        throw new InvalidArgumentException('Tipo de catálogo de imagen no válido.');
    }

    public static function directory($type)
    {
        self::assetType($type);
        if ($type === self::ITEM) return 'items';
        if ($type === self::MONSTER) return 'monsters';
        if ($type === self::CHARACTER_CLASS) return 'character-classes';

        return 'worlds';
    }

    public static function assertModel($type, Model $model)
    {
        if (($type === self::ITEM && $model instanceof Item)
            || ($type === self::MONSTER && $model instanceof Monster)
            || ($type === self::WORLD && $model instanceof World)
            || ($type === self::CHARACTER_CLASS && $model instanceof CharacterClass)) {
            return;
        }

        throw new InvalidArgumentException('El modelo no corresponde al tipo de imagen de catálogo.');
    }
}
