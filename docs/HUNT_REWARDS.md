# Recompensas pendientes de cacería

Una línea no apilable conserva su cantidad hasta el claim. Allí cada unidad recibe procedencia `hunt_reward_item`, índice unitario e identidad propia; no se agrega a `character_items`.

Una victoria completa producida por una `HuntingSession` procesa una única recompensa pendiente por Hunt. `UNIQUE(hunt_id)` es la garantía principal de idempotencia. Los Hunts manuales, derrotas y draws no generan recompensas.

La cabecera `HuntReward` se conserva incluso cuando las tiradas no producen objetos. Esa cabecera vacía marca que el loot terminó de procesarse y evita consumir RNG nuevamente. Permanece `pending` con `claimed_at` nulo; un futuro proceso de reclamación decidirá cómo cerrar las cabeceras vacías.

Cada `HuntRewardItem` conserva un drop y su enemigo fuente mediante `hunt_enemy_id` e `instance_identifier`. Monstruos repetidos comparten el catálogo precargado, pero ejecutan tiradas independientes. Los nombres y códigos de Item se guardan como snapshots históricos y las líneas de fuentes diferentes no se agregan en persistencia.

La cabecera conserva también snapshots de `gold_amount` y `experience_amount`. La experiencia procede de `Monster.experience_reward`; el oro se genera por enemigo desde el rango entero inclusivo `Monster.gold_min`/`gold_max`, siempre en orden de posición. Los rangos actuales son balance provisional. Mantenerlos en `Monster` es una solución MVP que podrá evolucionar a un catálogo `RewardProfile` sin reinterpretar recompensas históricas.

Hunt, enemigos, recompensa, líneas, contadores y cooldown se confirman en una sola transacción. Eliminar un Hunt en mantenimiento elimina su agregado Reward; las líneas usan RESTRICT hacia HuntEnemy e Item para preservar historial. Detener o expirar la sesión no elimina recompensas.

Generar no significa reclamar. El tick solo persiste la recompensa pendiente; la entrega ocurre exclusivamente mediante el claim global autoritativo.
# Relación con capacidad

Solo las recompensas `pending` participan en la proyección. El cálculo reúne todas las sesiones del personaje y agrega por `item_id`; no entrega objetos ni cambia el estado de la recompensa.
