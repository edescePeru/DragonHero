<?php
namespace App\Domain\WorldMaps;
use App\Models\WorldMap;use InvalidArgumentException;
final class WorldMapValidator
{
    private $images;
    public function __construct(WorldMapImageService $images){$this->images=$images;}
    public function validate(WorldMap $map){$contexts=($map->world_id?1:0)+($map->region_id?1:0);if($contexts!==1)throw new InvalidArgumentException('Selecciona un Mundo o una Región, pero no ambos.');if(!in_array($map->status,WorldMapStatus::values(),true))throw new InvalidArgumentException('Estado de mapa inválido.');if((int)$map->version<1)throw new InvalidArgumentException('Versión de mapa inválida.');if($map->is_default&&$map->status!==WorldMapStatus::ACTIVE)throw new InvalidArgumentException('El mapa predeterminado debe estar activo.');if($map->status===WorldMapStatus::ACTIVE)$this->images->validateActiveImage($map);}
}
