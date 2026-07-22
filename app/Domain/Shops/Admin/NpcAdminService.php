<?php

namespace App\Domain\Shops\Admin;

use App\Domain\Media\MediaAssetType;
use App\Domain\Shops\ShopCatalogValidator;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Npc;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class NpcAdminService
{
    private $validator;
    private $media;

    public function __construct(ShopCatalogValidator $validator, ShopMediaService $media)
    {
        $this->validator = $validator;
        $this->media = $media;
    }

    public function save(array $data, Npc $npc = null)
    {
        $file = isset($data['npc_portrait']) ? $data['npc_portrait'] : null;
        $remove = !empty($data['remove_npc_portrait']);
        unset($data['npc_portrait'], $data['remove_npc_portrait']);
        $data['code'] = $this->code($data['code']);

        return $this->media->persist(function () use ($data, $npc) {
            $model = $npc && $npc->exists
                ? Npc::whereKey($npc->id)->lockForUpdate()->firstOrFail()
                : new Npc();

            $duplicate = Npc::where('code', $data['code'])
                ->when($model->exists, function ($query) use ($model) {
                    $query->where('id', '<>', $model->id);
                })->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('El código normalizado del NPC ya existe.');
            }

            $model->fill($data);
            $this->validator->validateNpc($model);
            $model->save();

            return $model;
        }, [
            MediaAssetType::PORTRAIT => ['file' => $file, 'remove' => $remove],
        ]);
    }

    public function activate(Npc $npc)
    {
        return $this->status($npc, CatalogStatus::ACTIVE);
    }

    public function deactivate(Npc $npc)
    {
        return $this->status($npc, CatalogStatus::INACTIVE);
    }

    private function status(Npc $npc, $status)
    {
        $npc->status = $status;

        return $this->save($npc->only(['code', 'name', 'greeting', 'status']), $npc);
    }

    private function code($value)
    {
        $code = Str::slug(trim((string) $value));

        if ($code === '' || strlen($code) > 64) {
            throw new InvalidArgumentException('Código de NPC inválido.');
        }

        return $code;
    }
}
