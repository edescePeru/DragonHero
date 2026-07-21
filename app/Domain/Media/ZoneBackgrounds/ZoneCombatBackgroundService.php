<?php

namespace App\Domain\Media\ZoneBackgrounds;

use App\Domain\Media\MediaAssetService;
use App\Domain\Media\MediaAssetType;
use App\Models\MediaAsset;
use App\Models\Zone;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ZoneCombatBackgroundService
{
    const DISK = 'public';
    const MAX_BYTES = 5242880;
    const MAX_DIMENSION = 7680;
    const MAX_PIXELS = 33177600;

    private $media;

    public function __construct(MediaAssetService $media)
    {
        $this->media = $media;
    }

    public function persist(Closure $saveZone, UploadedFile $file = null, $remove = false)
    {
        if ($file && $remove) {
            throw new ZoneCombatBackgroundException('No puedes reemplazar y eliminar el escenario al mismo tiempo.');
        }

        $stored = $file ? $this->store($file) : null;
        $previous = null;

        try {
            $zone = DB::transaction(function () use ($saveZone, $stored, $remove, &$previous) {
                $zone = $saveZone();
                if (!$zone instanceof Zone || !$zone->exists) {
                    throw new ZoneCombatBackgroundException('No se pudo asociar el escenario con la Zone.');
                }
                if ($stored) {
                    $result = $this->media->replacePrimary($zone, array_merge($stored, [
                        'asset_type' => MediaAssetType::BACKGROUND,
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]));
                    $previous = $result['previous'];
                } elseif ($remove) {
                    $previous = $this->media->deletePrimary($zone, MediaAssetType::BACKGROUND);
                }
                return $zone;
            }, 3);
        } catch (Throwable $exception) {
            if ($stored) $this->deletePath($stored['disk'], $stored['path'], false);
            throw $exception;
        }

        if ($previous) $this->deletePath($previous->disk, $previous->path, true);
        return $zone;
    }

    public function presentation(Zone $zone)
    {
        if (!$zone->exists) return $this->emptyPresentation();
        $asset = $zone->primaryMediaAsset(MediaAssetType::BACKGROUND)->orderBy('id')->first();
        if (!$asset || !$this->fileExists($asset)) return $this->emptyPresentation();
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];
        return [
            'exists' => true,
            'url' => $asset->url(),
            'name' => isset($metadata['original_name']) ? (string) $metadata['original_name'] : basename($asset->path),
            'width' => (int) $asset->width,
            'height' => (int) $asset->height,
            'size_bytes' => (int) $asset->file_size,
            'size_label' => $this->sizeLabel((int) $asset->file_size),
            'format' => $this->formatLabel($asset->mime_type),
        ];
    }

    private function store(UploadedFile $file)
    {
        $info = $this->inspect($file);
        $path = 'game-assets/zones/'.date('Y/m').'/'.(string) Str::uuid().'/background.'.$info['extension'];
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if ($stream === false || !Storage::disk(self::DISK)->put($path, $stream)) throw new ZoneCombatBackgroundException('No se pudo almacenar el escenario de combate.');
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
        return ['disk' => self::DISK, 'path' => $path, 'mime_type' => $info['mime'], 'width' => $info['width'], 'height' => $info['height'], 'file_size' => $info['size'], 'metadata' => ['zone_combat_background_version' => 1, 'original_name' => $file->getClientOriginalName(), 'recommended_ratio' => '16:9', 'recommended_resolution' => '1920x1080']];
    }

    private function inspect(UploadedFile $file)
    {
        $path = $file->getRealPath(); $size = $file->getSize();
        if (!$file->isValid() || !is_string($path) || !is_file($path) || $size <= 0 || $size > self::MAX_BYTES) throw new ZoneCombatBackgroundException('El escenario no es válido o supera 5 MB.');
        $fileMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path); $dimensions = @getimagesize($path);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!$dimensions || !isset($dimensions['mime']) || !isset($allowed[$fileMime]) || !isset($allowed[$dimensions['mime']]) || $fileMime !== $dimensions['mime']) throw new ZoneCombatBackgroundException('Solo se permiten imágenes PNG, JPG o WebP válidas.');
        $width = (int) $dimensions[0]; $height = (int) $dimensions[1];
        if ($width <= 0 || $height <= 0 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || $width * $height > self::MAX_PIXELS) throw new ZoneCombatBackgroundException('La imagen supera las dimensiones permitidas.');
        $decoder = ['image/png' => 'imagecreatefrompng', 'image/jpeg' => 'imagecreatefromjpeg', 'image/webp' => 'imagecreatefromwebp'];
        $image = @call_user_func($decoder[$fileMime], $path);
        if (!$image) throw new ZoneCombatBackgroundException('El archivo de escenario está corrupto.');
        imagedestroy($image);
        return ['mime' => $fileMime, 'extension' => $allowed[$fileMime], 'width' => $width, 'height' => $height, 'size' => (int) $size];
    }

    private function emptyPresentation(){return ['exists'=>false,'url'=>null,'name'=>null,'width'=>null,'height'=>null,'size_bytes'=>null,'size_label'=>null,'format'=>null];}
    private function fileExists(MediaAsset $asset){try{return Storage::disk($asset->disk)->exists($asset->path);}catch(Throwable $exception){return false;}}
    private function deletePath($disk,$path,$warn){try{$deleted=Storage::disk($disk)->delete($path);if(!$deleted&&$warn)Log::warning('No se pudo eliminar un escenario de Zone reemplazado.',['path'=>$path]);}catch(Throwable $exception){if($warn)Log::warning('Falló la limpieza de un escenario de Zone reemplazado.',['path'=>$path]);}}
    private function sizeLabel($bytes){if($bytes>=1048576)return round($bytes/1048576,2).' MB';return max(1,(int)ceil($bytes/1024)).' KB';}
    private function formatLabel($mime){if($mime==='image/jpeg')return'JPG';if($mime==='image/png')return'PNG';if($mime==='image/webp')return'WEBP';return strtoupper((string)$mime);}
}
