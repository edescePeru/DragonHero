<?php

namespace App\Domain\WorldMaps;

use App\Models\WorldMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class WorldMapImageService
{
    private const MIME_EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function store(UploadedFile $file)
    {
        if (!$file->isValid()) throw new InvalidArgumentException('La imagen subida no es válida.');
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > (int) config('world_maps.max_file_size_kb', 5120) * 1024) throw new InvalidArgumentException('La imagen supera el tamaño máximo configurado.');
        $info = @getimagesize($file->getRealPath());
        if (!$info || !isset($info[0], $info[1], $info['mime'])) throw new InvalidArgumentException('El archivo subido no es una imagen válida.');
        $mime = (string) $info['mime'];
        if (!isset(self::MIME_EXTENSIONS[$mime])) throw new InvalidArgumentException('La imagen debe ser PNG, JPEG o WebP.');
        $width = (int) $info[0]; $height = (int) $info[1];
        if ($width < (int) config('world_maps.minimum_width', 320) || $height < (int) config('world_maps.minimum_height', 180)) throw new InvalidArgumentException('Las dimensiones de la imagen son demasiado pequeñas.');
        $disk = (string) config('world_maps.disk', 'public');
        $this->assertDiskConfigured($disk);
        $path = 'world-maps/'.date('Y/m').'/'.Str::uuid().'.'.self::MIME_EXTENSIONS[$mime];
        $stream = fopen($file->getRealPath(), 'rb');
        try { $written = Storage::disk($disk)->put($path, $stream); } finally { if (is_resource($stream)) fclose($stream); }
        if (!$written || !$this->exists($disk, $path)) throw new InvalidArgumentException('La imagen del mapa no pudo almacenarse.');
        return ['image_disk'=>$disk,'image_path'=>$path,'original_width'=>$width,'original_height'=>$height,'mime_type'=>$mime,'file_size'=>$size];
    }

    public function exists($disk, $path)
    {
        if (!$this->isRelativePath($path)) return false;
        try { $this->assertDiskConfigured($disk); return Storage::disk($disk)->exists($path); } catch (\Throwable $e) { return false; }
    }

    public function url($disk, $path)
    {
        if (!$this->isRelativePath($path)) return null;
        try { $this->assertDiskConfigured($disk); $url = Storage::disk($disk)->url($path); return is_string($url) && trim($url) !== '' ? $url : null; } catch (\Throwable $e) { return null; }
    }

    public function metadata(WorldMap $map)
    {
        $exists=$this->exists($map->image_disk,$map->image_path); $url=$this->url($map->image_disk,$map->image_path); $width=(int)$map->original_width; $height=(int)$map->original_height;
        return ['disk'=>(string)$map->image_disk,'path'=>(string)$map->image_path,'exists'=>$exists,'url'=>$url,'public_url_available'=>$exists&&$url!==null,'original_width'=>$width,'original_height'=>$height,'aspect_ratio'=>$height>0?$width/$height:null,'mime_type'=>(string)$map->mime_type,'file_size'=>(int)$map->file_size];
    }

    public function validateActiveImage(WorldMap $map)
    {
        if (!$map->image_disk || !$this->isRelativePath($map->image_path)) throw new InvalidArgumentException('Un mapa activo requiere una imagen válida.');
        if ((int)$map->original_width<=0 || (int)$map->original_height<=0) throw new InvalidArgumentException('Un mapa activo requiere dimensiones de imagen válidas.');
        if (!isset(self::MIME_EXTENSIONS[(string)$map->mime_type])) throw new InvalidArgumentException('Un mapa activo requiere una imagen PNG, JPEG o WebP.');
        if (!$this->exists($map->image_disk,$map->image_path) || $this->url($map->image_disk,$map->image_path)===null) throw new InvalidArgumentException('Un mapa activo requiere una imagen accesible en el disk configurado.');
    }

    public function delete($disk,$path){return $disk&&$path?Storage::disk($disk)->delete($path):false;}
    public function aspectChanged($oldWidth,$oldHeight,$newWidth,$newHeight){if(!$oldWidth||!$oldHeight)return false;$old=$oldWidth/$oldHeight;$new=$newWidth/$newHeight;return abs($old-$new)/$old>(float)config('world_maps.aspect_ratio_tolerance',0.02);}
    private function assertDiskConfigured($disk){if(!is_string($disk)||trim($disk)===''||!config('filesystems.disks.'.$disk))throw new InvalidArgumentException('El disk de imágenes del mapa no está configurado.');}
    private function isRelativePath($path){return is_string($path)&&$path!==''&&trim($path)===$path&&strpos($path,"\0")===false&&strpos($path,'\\')===false&&strpos($path,'://')===false&&strpos($path,'../')===false&&substr($path,0,1)!=='/'&&!preg_match('/^[A-Za-z]:/',$path);}
}
