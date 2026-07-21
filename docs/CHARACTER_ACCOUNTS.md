# Cuentas y selección de personajes

Cada cuenta puede conservar hasta tres personajes y exactamente uno puede ser el contexto activo. `CharacterAccountLimit` centraliza el límite y `ActiveCharacterContext` es la única autoridad para consultar, seleccionar o limpiar ese contexto. La selección nunca se infiere del primer personaje ni de un ID incluido en una URL.

Tras autenticar, una cuenta vacía entra en `characters.create`; una cuenta con personajes entra siempre en `characters.select`, incluso si conserva una selección anterior. Crear un personaje lo selecciona dentro de la misma transacción. Las rutas jugables exigen propiedad y coincidencia con el personaje activo.

## Nombre canónico

`name` conserva la presentación elegida, con espacios exteriores eliminados e interiores colapsados. `normalized_name` es la autoridad única global: aplica minúsculas Unicode y, cuando `intl` está disponible, NFC. La collation `utf8mb4_unicode_ci` hace la comparación case-insensitive y accent-insensitive; por ello `José`, `JOSE` y `josé` son equivalentes. Los nombres heredados se normalizan sin imponerles retrospectivamente las reglas nuevas.

## Aislamiento

Oro, ledger, inventario, instancias, equipamiento, Hunts, sesiones, recompensas, experiencia y salud continúan perteneciendo al `Character`. Cambiar selección no transfiere ni combina recursos. No existe baúl compartido. Transferencias, renombrado y eliminación están fuera de alcance.

## Plantillas y presentación

La plantilla administrada define clase, `presentation_gender`, `body_type`, imagen y estadísticas iniciales. `presentation_gender` admite `male`, `female` y `neutral`; nunca se infiere desde códigos o nombres. El cliente envía únicamente `name` y `template_id`. Los personajes heredados sin plantilla conservan todos sus datos y utilizan el fallback visual vertical.
