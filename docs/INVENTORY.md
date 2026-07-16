# Inventario básico

## Cantidades

`quantity` es la cantidad total agregada que posee el personaje. `locked_quantity` es la parte reservada para operaciones futuras. `available_quantity = quantity - locked_quantity` se calcula en el dominio y nunca se persiste.

`character_items` representa un total agregado, no una pila física. Por eso `quantity` puede superar `Item.max_stack`: un item con `max_stack = 99` puede tener `quantity = 437`. `max_stack` queda reservado para futuros slots, pilas físicas o `item_instances`.

Las filas con total cero se eliminan para conservar una representación única y evitar registros vacíos.

## Seguridad e invariantes

Todas las escrituras usan transacciones, bloqueo de la fila del personaje y `lockForUpdate` sobre catálogo e inventario. Solo se permiten items `active` y apilables. La base impide duplicados por personaje/item.

La FK de item usa RESTRICT: un item referenciado no puede eliminarse físicamente. Los items dejan de utilizarse marcándolos `inactive` o `hidden`.

## Alcance futuro

Los objetos no apilables se representan ahora mediante `ItemInstance`; `character_items` queda reservado a clasificaciones coherentes apilables. Refinamiento, evolución, equipamiento y comercio siguen fuera de alcance. Véase `ITEM_INSTANCES.md`.

Loot, crafteo, mercado y misiones reutilizarán `InventoryService`. Las instancias únicas y equipo usarán posteriormente `item_instances`. Un futuro `item_transactions` proporcionará auditoría económica; no forma parte de este incremento.

El equipamiento básico separa las instancias `available`, visibles y contadas en mochila, de las `equipped`, visibles en el snapshot de equipo y fuera de la mochila. Desequipar exige espacio físico, pero no conservar la reserva adicional de cacería.
