<?php
namespace App\Domain\WorldMaps\Exceptions;
use InvalidArgumentException;
final class WorldMapAspectRatioConfirmationException extends InvalidArgumentException
{
    public function __construct(){parent::__construct('La nueva imagen tiene una proporción diferente. Los polígonos conservarán sus coordenadas, pero debes revisarlos visualmente después del reemplazo.');}
}
