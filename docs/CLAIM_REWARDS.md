# Claim de recompensas

El claim construye primero un plan validado: cantidades apilables van a `InventoryService`; cada unidad no apilable va a `ItemInstanceService` y genera un evento de nacimiento. Un `operation_uuid` v5 determinista agrupa instancias, eventos, movimiento de oro y resultado. Ningún valor procede del cliente.

El claim transfiere todas las `HuntReward` pending del Character o ninguna: objetos, oro y experiencia forman una sola operación. El orden de locks es Character, `character_items`, cabeceras pending, líneas, Items y billetera. El catálogo de niveles es de solo lectura y se consulta normalmente dentro de la transacción. `InventoryService::addManyLocked()`, `WalletService::creditLocked()` y `CharacterProgressionService::grantExperienceLocked()` exigen una transacción activa y el estado correspondiente previamente bloqueado.

La falta de capacidad impide entregar cualquier recurso. El oro de todas las cabeceras se agrega en un único movimiento de ledger y la experiencia acumulada puede producir varias subidas de nivel. Si falla inventario, billetera, progresión o transición de estado, MySQL revierte el claim completo.

La capacidad reutiliza los calculadores existentes y para reclamar exige `projectedUsedSlots <= effectiveCapacity`; la reserva preventiva solo controla si la cacería puede continuar. Las cabeceras vacías también pasan a claimed.

Un Item desactivado después de obtener el loot puede reclamarse si todavía existe y conserva un `max_stack` válido. Los no apilables se representan temporalmente como cantidad agregada con `max_stack = 1`, por lo que cada unidad consume un slot. Esto no implementa instancias, equipamiento, durabilidad, mejoras, sockets ni objetos ligados.

El DTO agregado muestra código y nombre actuales del Item; los snapshots históricos permanecen en `hunt_reward_items`. El resultado incluye oro, experiencia y cambios de nivel confirmados. El claim no cambia ni reactiva HuntingSession.

`CharacterInventorySummaryService` es la lectura compartida por inventario y HuntingSession. Separa `inventory_items`, `inventory_status` real y `pending_projection`. Los ticks no transportan el inventario completo; el GET y la respuesta del claim sí devuelven snapshots controlados. Los resúmenes `session_pending_rewards_summary` y `character_pending_rewards_summary` mantienen significados distintos.
