# Plantillas base de personaje

`CharacterTemplate` define la clase, el tipo corporal, la imagen corporal base y las estadísticas iniciales de una nueva creación. `CharacterClass` conserva capacidades y restricciones de equipamiento; `Character` pertenece al jugador y evoluciona de forma independiente.

Las seis estadísticas persistidas se copian una sola vez: vida máxima, ataque, defensa, precisión, evasión y crítico. El crítico usa el mismo porcentaje decimal `DECIMAL(5,2)` que `characters` (por ejemplo, `5.00` significa 5 %). Editar una plantilla nunca sincroniza personajes existentes.

## Contrato visual

`base_visual` es un cuerpo completo frontal y no es un `portrait`. Usa canvas 512 × 768 (2:3), origen superior izquierdo y línea de pies común. Se generan WebP 128 × 192, 256 × 384 y 512 × 768 con calidad 82, además de conservar el original. PNG y WebP preservan transparencia; JPEG se admite conservando su fondo. No se recorta, rellena, recentra, deforma ni elimina fondos.

Los archivos viven en `game-assets/character-templates/YYYY/MM/{uuid}` y se referencian mediante `media_assets` con morph alias `character_template`. Los personajes sin plantilla usan un fallback vertical. Los retratos cuadrados existentes siguen reservados para avatar o perfil.

Solo una plantilla `active`, con clase activa y todas sus variantes físicas válidas, puede crear personajes. `inactive` y `hidden` no son seleccionables. Tras migrar debe crearse y activarse al menos una plantilla desde Admin Content; `CharacterTemplateDevelopmentSeeder` es explícito y no se ejecuta en producción.

## Presentación pública

`presentation_gender` es independiente de clase y `body_type`. Admite `male`, `female` y `neutral`; una plantilla nueva o activada debe configurarlo explícitamente y nunca se infiere desde nombre, código, descripción o cuerpo. El flujo público envía únicamente `name` y `template_id`.

## Futuro

Las capas futuras deberán declarar `body_type`, canvas 512 × 768, capa, orden Z, mismo origen y misma línea de pies. Esta fase no incluye armaduras superpuestas, armas, mascotas, offsets, compositor, canvas, animación ni integración en Hunting.
# Contrato visual de body type

`body_type` no se infiere desde género, clase, código o nombre. Las plantillas que comparten un valor deben compartir canvas 512 × 768, origen y alineación, porque reutilizan las mismas capas equipadas. Véase [CHARACTER_APPEARANCE.md](CHARACTER_APPEARANCE.md).
