<?php

namespace App\Domain\Characters\Classes;

use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\CharacterClass;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CharacterClassAdminService
{
    private $validator;
    private $images;

    public function __construct(CharacterClassValidator $validator, CatalogImageService $images)
    {
        $this->validator = $validator;
        $this->images = $images;
    }

    public function save(array $data, CharacterClass $class = null, UploadedFile $icon = null)
    {
        unset($data['icon']);
        $saved = DB::transaction(function () use ($data, $class) {
            $this->lockCatalog();
            $model = $class && $class->exists
                ? CharacterClass::whereKey($class->id)->lockForUpdate()->firstOrFail()
                : new CharacterClass();
            $model->fill([
                'code' => strtolower(trim($data['code'])),
                'name' => trim($data['name']),
                'description' => isset($data['description']) && trim((string) $data['description']) !== '' ? trim($data['description']) : null,
                'status' => $data['status'],
                'sort_order' => (int) $data['sort_order'],
                'can_dual_wield' => (bool) $data['can_dual_wield'],
            ]);
            $this->validator->validate($model);
            $this->assertActiveClassRemains($model);
            $model->save();
            return $model;
        }, 3);

        if ($icon) $this->images->replace($saved, CatalogImageType::CHARACTER_CLASS, $icon);
        return $saved->fresh();
    }

    public function setStatus(CharacterClass $class, $status)
    {
        if (!in_array($status, CatalogStatus::values(), true)) throw new InvalidArgumentException('Estado no válido.');
        return DB::transaction(function () use ($class, $status) {
            $this->lockCatalog();
            $locked = CharacterClass::whereKey($class->id)->lockForUpdate()->firstOrFail();
            $locked->status = $status;
            $this->validator->validate($locked);
            $this->assertActiveClassRemains($locked);
            $locked->save();
            return $locked;
        }, 3);
    }

    public function deleteIcon(CharacterClass $class)
    {
        $this->images->delete($class, CatalogImageType::CHARACTER_CLASS);
    }

    private function lockCatalog()
    {
        CharacterClass::orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function assertActiveClassRemains(CharacterClass $candidate)
    {
        if ($candidate->status === CatalogStatus::ACTIVE) return;
        $active = CharacterClass::where('status', CatalogStatus::ACTIVE);
        if ($candidate->exists) $active->where('id', '<>', $candidate->id);
        if (!$active->exists()) throw new InvalidArgumentException('Debe existir al menos una clase de personaje activa.');
    }
}
