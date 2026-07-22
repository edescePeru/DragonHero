<?php

namespace App\Domain\Characters\Classes;

use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\CharacterClass;
use InvalidArgumentException;

final class CharacterClassValidator
{
    public function validate(CharacterClass $class)
    {
        if (!preg_match('/^[a-z0-9_-]{2,64}$/', (string) $class->code)) {
            throw new InvalidArgumentException('El código debe usar minúsculas, números, guion o guion bajo.');
        }
        if (trim((string) $class->name) === '') throw new InvalidArgumentException('El nombre es obligatorio.');
        if (!in_array($class->status, CatalogStatus::values(), true)) throw new InvalidArgumentException('El estado de la clase no es válido.');
        if ((int) $class->sort_order < 0) throw new InvalidArgumentException('El orden no puede ser negativo.');
        return $class;
    }
}
