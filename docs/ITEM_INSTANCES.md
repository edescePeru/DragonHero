# Objetos únicos y trazabilidad

`Item` es la plantilla de catálogo. Una clasificación válida exige simultáneamente `is_stackable = true` y `max_stack > 1`, o `is_stackable = false` y `max_stack = 1`. Cualquier otra combinación es inválida.

`character_items` conserva cantidades agregadas apilables. `ItemInstance` representa una unidad física no apilable con UUID público completo, propietario, plantilla, refinamiento, estado y procedencia unitaria inmutable. La referencia corta es exclusivamente visual y nunca identifica escrituras.

El namespace UUID v5 fijo de DragonHero es `8f4c1c72-7d7b-5d1a-9a4e-2d9c0f7b6a31`. Las entradas están versionadas: `legacy-item-instance:v1:{character_item_id}:{unit_index}` y `hunt-reward-claim:v1:{character_id}:{reward_ids_ordenados}`. La unicidad `(origin_type, origin_id, origin_unit_index)` es la protección económica primaria contra duplicación.

Una compra crea objetos únicos exclusivamente mediante `ItemInstanceService::createFromShopPurchaseLocked()`. Usa origen `shop_purchase`, evento `created_from_shop_purchase` y UUID v5 `shop-purchase-item-instance:v1:{purchase_id}:{unit_index}`. Los replays de compra no vuelven a invocar esta creación.

Los eventos son append-only por contrato de servicios y modelo. No son criptográficamente inmutables y Eloquent no protege contra Query Builder o SQL directo; la seguridad depende además de permisos limitados, ausencia de endpoints de edición, transacciones, locks y revisiones de integridad.

El orden global de locks es Character, `character_items`, `item_instances`, rewards y líneas, catálogo, Wallet y progresión. Futuras transferencias, equipamiento y refinamiento deberán respetarlo.

La importación heredada usa Query Builder y UUID deterministas, sin modelos ni servicios. Su reversión solo es segura mientras propietario, Item, refinamiento, estado y único evento de importación permanezcan intactos. Tras comercio, evolución o refinamiento puede ser funcionalmente irreversible.

Eventos futuros documentados, no implementados: `refined`, `evolved`, `equipped`, `unequipped`, `traded`, `sold`, `destroyed`, `bound` y `admin_adjusted`. Las recetas futuras configurarán reset o conservación de refinamiento, opciones, sockets y binding. Los objetos apilables requerirán un `InventoryMovementLedger` separado.

`equipped` y `unequipped` ya están implementados por el módulo de equipamiento. `available` ocupa mochila y `equipped` no; ambos estados deben permanecer sincronizados con `character_equipment`. El orden de locks incorpora esta tabla después de las instancias y antes del catálogo.
# Refinamiento

El nivel `refinement_level` se modifica exclusivamente mediante `ItemRefinementService`. La operación conserva UUID, origen, propietario, estado y equipamiento, y escribe un evento append-only `refinement_succeeded`. En v1 el refinamiento no aporta estadísticas.
