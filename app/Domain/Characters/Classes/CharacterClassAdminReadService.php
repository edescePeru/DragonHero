<?php

namespace App\Domain\Characters\Classes;

use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\CharacterClass;

final class CharacterClassAdminReadService
{
    private $images;

    public function __construct(CatalogImageService $images){$this->images = $images;}

    public function listing()
    {
        $paginator = CharacterClass::with(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::ICON)->where('is_primary', true)->orderBy('id');
        }])->withCount(['characterTemplates', 'characters', 'items'])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')->paginate(25);
        $paginator->setCollection($paginator->getCollection()->map(function ($class) {
            return $this->row($class);
        }));
        return $paginator;
    }

    public function detail(CharacterClass $class)
    {
        $class->loadMissing(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::ICON)->where('is_primary', true)->orderBy('id');
        }]);
        $class->loadCount(['characterTemplates', 'characters', 'items']);
        return $this->row($class);
    }

    public function emptyRow()
    {
        $class = new CharacterClass(['status' => CatalogStatus::HIDDEN, 'sort_order' => 0, 'can_dual_wield' => false]);
        return ['model' => $class, 'icon' => $this->images->presentation(CatalogImageType::CHARACTER_CLASS)];
    }

    public function statuses(){return CatalogStatus::values();}

    private function row(CharacterClass $class)
    {
        return ['model' => $class, 'icon' => $this->images->presentationFor($class, CatalogImageType::CHARACTER_CLASS)];
    }
}
