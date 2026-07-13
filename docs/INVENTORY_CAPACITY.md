# Capacidad de inventario

La cantidad de unidades almacenada en `character_items` sigue siendo agregada. `items.max_stack` no limita esa cantidad: define cuántas unidades ocupan un slot derivado. Todo objeto inventariable exige `max_stack >= 1`; un objeto no apilable usa `1`. Los valores históricos `NULL` se migran a `1` y la columna queda `NOT NULL`.

Los slots usados por un objeto son `ceil(cantidad / max_stack)`, calculados con enteros. Para capacidad se agregan por `item_id` el inventario actual y las líneas de todas las recompensas `pending` de todas las sesiones del personaje. Los snapshots históricos no separan pilas. Recompensas vacías aportan cero.

La capacidad efectiva es la base más grants vigentes. Un grant está vigente si está activo, comenzó (o no tiene inicio) y no terminó; `ends_at` es exclusivo. Si no hay `starts_at`, rige desde `granted_at` hasta `ends_at`. `granted_at` procede del servidor. Los permanentes no tienen fin; los temporales sí. La política MVP exige que base más todos los grants activos, incluso futuros, no supere 200.

La base inicial actual es 30, no un máximo. La progresión puede producir 40, 50 o 60; 200 es solo el límite técnico. La reserva preventiva centralizada actual es 5. `claimFits` indica si toda la proyección cabe; `huntingCanContinue` exige además conservar la reserva. Si vence un bonus y el inventario excede la capacidad, se informa el déficit sin borrar objetos, grants ni recompensas.

No existe transferencia ni reclamación en esta fase. El futuro claim debe reutilizar `PendingRewardCapacityService` e `InventorySlotCalculator` y recalcular bajo locks; no debe introducir otra fórmula.

Orden de locks de sesión: Character, HuntingSession cuando existe, Zone/catálogo y luego recursos de capacidad (`character_items`, rewards pending y líneas). Las lecturas GET son snapshots informativos sin locks. `InventoryService` permanece como única autoridad de mutación del inventario y no se usa para simular slots.
