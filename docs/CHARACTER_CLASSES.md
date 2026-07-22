# Clases de personaje

`CharacterClass` es la autoridad de identidad, disponibilidad, restricciones de Items y capacidades generales de una clase. `CharacterTemplate` representa una configuración inicial concreta y `Character` conserva referencias estables tanto a su clase como a su plantilla.

El catálogo usa `active`, `inactive` y `hidden`. Debe existir siempre al menos una clase activa. Desactivar u ocultar conserva Characters, plantillas, restricciones y equipamiento histórico; no existe eliminación física desde el administrador.

`can_dual_wield` controla la capacidad de equipar dos armas de una mano mediante `CharacterClassCapabilityService`. Las estadísticas iniciales continúan perteneciendo a `CharacterTemplate`, no a la clase.

El icono opcional utiliza `MediaAssetType::ICON`, morph alias `character_class` y el procesador común de imágenes de catálogo. No existen columnas de ruta o URL en `character_classes`.

El código es un identificador técnico único y puede editarse porque el dominio relaciona clases por ID. La migración histórica que creó `adventurer` permanece inmutable; factories y seeders operativos seleccionan clases activas por ID.

Una plantilla que ya creó Characters no puede cambiar de clase: hacerlo produciría una discrepancia histórica entre `characters.character_class_id` y la plantilla referenciada.
