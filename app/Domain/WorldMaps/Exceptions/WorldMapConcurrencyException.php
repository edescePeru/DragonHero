<?php
namespace App\Domain\WorldMaps\Exceptions;use RuntimeException;final class WorldMapConcurrencyException extends RuntimeException{public function __construct(){parent::__construct('El mapa o área fue modificado por otro administrador. Recarga antes de continuar.');}}
