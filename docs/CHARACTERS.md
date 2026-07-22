# Characters

## Alcance inicial

Un usuario tiene una relación `hasMany` con personajes, pero durante esta fase solo puede crear uno. Este límite se aplica de forma autoritativa en `CreateCharacterAction` dentro de una transacción que bloquea la fila del usuario.

No se incluyen clases, razas, profesiones, inventario, equipamiento, economía ni combate.

## Identidad y unicidad

El nombre del personaje es único globalmente entre todos los usuarios. La columna usa `utf8mb4_unicode_ci`, por lo que MySQL aplica la unicidad sin distinguir mayúsculas y minúsculas: por ejemplo, `Dragon` y `dragon` representan el mismo nombre.

Antes de validar y guardar, se eliminan espacios exteriores y las secuencias de espacios interiores se normalizan a un solo espacio.

## Persistencia

Las tablas de juego usan InnoDB para permitir transacciones, claves foráneas y bloqueos de fila. La tabla `users` se convierte permanentemente a InnoDB; la migración no vuelve a MyISAM porque hacerlo rompería estas garantías y futuras claves foráneas.

`base_critical_rate` se almacena como `DECIMAL(5,2) UNSIGNED` y se trata como decimal, no como float.

## Estadísticas efectivas

Las columnas `base_*` representan exclusivamente las estadísticas naturales almacenadas. Las estadísticas efectivas se calculan en memoria mediante `CharacterStatsCalculator` y no se persisten.

Los porcentajes usan escala de 0 a 100. El multiplicador crítico y la velocidad de ataque no son porcentajes. Los cálculos internos conservan toda la precisión disponible; el objeto inmutable `CharacterStats` redondea a dos decimales al exponer tasas, bonus y multiplicadores. `power` se expone como entero redondeado. El formato visual pertenece a Blade y el dominio no usa `number_format`.

La reducción provisional es `(defense / (defense + 100)) * 100`, limitada a 75 %. El valor 100, el límite y todos los pesos del power están centralizados en `CharacterStatsCalculator`.

El power provisional combina vida máxima, ataque, defensa, precisión, evasión, crítico, multiplicador crítico, velocidad de ataque y reducción de daño. No representa daño final ni una probabilidad contextual de acertar o esquivar.

El equipamiento confirmado se suma en esta misma autoridad. La ficha recibe base, aporte del equipo y valor efectivo ya preparados; Blade no consulta Items ni reproduce fórmulas. Véase `docs/CHARACTER_STATS.md`.

## Cuenta y personaje activo

Las reglas de límite de tres, selector, personaje activo, nombre canónico, legacy y aislamiento se documentan en `docs/CHARACTER_ACCOUNTS.md`.

## Progreso de experiencia

La experiencia del personaje es acumulada. `CharacterProgressionService` interpreta el catálogo de `CharacterLevelRequirement` tanto para conceder experiencia como para preparar el progreso visual, sin recalcular ni modificar el nivel durante una lectura. `CharacterExperienceProgress` entrega a la vista el requisito actual, el siguiente umbral, la experiencia ganada y faltante dentro del tramo y el porcentaje redondeado a dos decimales.

El nivel máximo activo se obtiene de la fila global tipada `character_progression_settings`; nunca pertenece al Character. La curva conserva umbrales acumulados continuos desde nivel 1 con 0 EXP. Las filas sobre el cap quedan preparadas para futuras ampliaciones y no son alcanzables. El mantenedor permite agregar niveles consecutivos sin elevar automáticamente el cap y retirar únicamente niveles finales preparados, nunca niveles activos o usados por personajes. Al llegar al cap, la EXP continúa acumulándose sin otorgar niveles superiores. El mantenedor `admin.content.progression.*` aplica altas, cambios y bajas finales de forma atómica, usa versión optimista y registra snapshots completos en `character_progression_revisions` con administrador y motivo.

La ficha no consulta el catálogo ni calcula porcentajes. Cuando no existe un nivel siguiente configurado, presenta el nivel máximo disponible y una barra completa; la experiencia acumulada puede continuar por encima del último umbral.

La ficha recibe también un snapshot controlado de los ocho slots de equipamiento. El equipo todavía no altera estadísticas efectivas; esa integración será un incremento independiente.
# Navegación principal

“Mi personaje” abre el Overview del Character activo. La ficha `characters.show` se conserva como detalle contextual, pero ya no es la entrada principal. La Home resuelve el Character mediante `ActiveCharacterContext`.
