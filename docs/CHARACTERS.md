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
