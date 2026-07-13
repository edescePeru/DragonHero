# Claim de recompensas

El claim transfiere todas las `HuntReward` pending del Character o ninguna. El orden de locks es Character, `character_items`, cabeceras pending, líneas e Items. `InventoryService::addManyLocked()` es una API interna: exige transacción activa y estado previamente bloqueado.

La capacidad reutiliza los calculadores existentes y para reclamar exige `projectedUsedSlots <= effectiveCapacity`; la reserva preventiva solo controla si la cacería puede continuar. Las cabeceras vacías también pasan a claimed.

Un Item desactivado después de obtener el loot puede reclamarse si todavía existe y conserva un `max_stack` válido. Los no apilables se representan temporalmente como cantidad agregada con `max_stack = 1`, por lo que cada unidad consume un slot. Esto no implementa instancias, equipamiento, durabilidad, mejoras, sockets ni objetos ligados.

El DTO agregado muestra código y nombre actuales del Item; los snapshots históricos permanecen en `hunt_reward_items`. El claim no entrega oro o experiencia y no cambia ni reactiva HuntingSession.

`CharacterInventorySummaryService` es la lectura compartida por inventario y HuntingSession. Separa `inventory_items`, `inventory_status` real y `pending_projection`. Los ticks no transportan el inventario completo; el GET y la respuesta del claim sí devuelven snapshots controlados. Los resúmenes `session_pending_rewards_summary` y `character_pending_rewards_summary` mantienen significados distintos.
