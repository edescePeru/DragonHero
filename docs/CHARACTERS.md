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
