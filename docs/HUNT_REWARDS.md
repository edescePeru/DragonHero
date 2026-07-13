# Recompensas pendientes de cacería

Una victoria completa producida por una `HuntingSession` procesa una única recompensa pendiente por Hunt. `UNIQUE(hunt_id)` es la garantía principal de idempotencia. Los Hunts manuales, derrotas y draws no generan recompensas.

La cabecera `HuntReward` se conserva incluso cuando las tiradas no producen objetos. Esa cabecera vacía marca que el loot terminó de procesarse y evita consumir RNG nuevamente. Permanece `pending` con `claimed_at` nulo; un futuro proceso de reclamación decidirá cómo cerrar las cabeceras vacías.

Cada `HuntRewardItem` conserva un drop y su enemigo fuente mediante `hunt_enemy_id` e `instance_identifier`. Monstruos repetidos comparten el catálogo precargado, pero ejecutan tiradas independientes. Los nombres y códigos de Item se guardan como snapshots históricos y las líneas de fuentes diferentes no se agregan en persistencia.

Hunt, enemigos, recompensa, líneas, contadores y cooldown se confirman en una sola transacción. Eliminar un Hunt en mantenimiento elimina su agregado Reward; las líneas usan RESTRICT hacia HuntEnemy e Item para preservar historial. Detener o expirar la sesión no elimina recompensas.

Generar no significa reclamar. En esta fase no se llama InventoryService ni WalletService, no se entregan Items, oro o experiencia y no existe endpoint de claim. El mismo diseño podrá incorporarse posteriormente al modo offline y a un claim con capacidad de inventario.
# Relación con capacidad

Solo las recompensas `pending` participan en la proyección. El cálculo reúne todas las sesiones del personaje y agrega por `item_id`; no entrega objetos ni cambia el estado de la recompensa.
