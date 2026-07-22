# Ventas de Items a Shops

Este incremento prepara exclusivamente la persistencia y la autoridad de precios. No existe todavía un endpoint ni una interfaz pública para vender.

## Configuración

- `items.is_sellable` habilita la venta a NPC y `items.sell_price` conserva el precio base entero en oro. Deshabilitar la venta no borra el precio.
- Solo `material`, `consumable`, `equipment` y `dragon_material` pueden habilitarse. `quest`, `key` y `currency_item` quedan protegidos.
- `shops.buys_items` habilita la capacidad futura de compra. `purchase_rate_basis_points` usa de 1 a 10000 puntos base (`10000 = 100%`) y se conserva al deshabilitarla.

El precio unitario autoritativo es `floor(sell_price * purchase_rate_basis_points / 10000)`, calculado exclusivamente con enteros y rechazado si resulta menor que 1 o excede el rango seguro de PHP 7.3. El total es `unit_gold * quantity`, también protegido contra overflow.

## Ledger futuro

`shop_sales` es un historial económico inmutable preparado para fuentes agregadas (`stack`) y únicas (`instance`). Conserva snapshots, una clave idempotente global y referencias con `RESTRICT`. No contiene estado porque una fila representa una venta confirmada.

El flujo futuro deberá comprobar disponibilidad y pertenencia, bloquear en orden estable, retirar mediante las autoridades actuales de inventario/instancias, acreditar una sola operación de Wallet y crear el ledger dentro de la misma transacción. Este incremento no ejecuta ninguna de esas mutaciones.

## Ciclo de vida de objetos únicos

Una `ItemInstance` vendida no se elimina: cambia una única vez de `available` a `sold` y conserva propietario, Item, origen, refinamiento y eventos para auditoría. `sold` es terminal; no puede volver a inventario, equiparse, refinarse, transferirse ni venderse otra vez. El jugador debe desequipar antes de vender.

La transición autoritativa exige una transacción activa y una instancia previamente bloqueada. Registra el evento append-only `sold_to_shop`, enlazado de forma mínima con `ShopSale`. Una instancia `sold` no ocupa capacidad ni aparece en las colecciones disponibles o en Character Overview. Todavía no existe venta pública ni acreditación de oro.

## Operación transaccional interna

El endpoint interno `POST /characters/{character}/shops/{shop}/sales` acepta exclusivamente la fuente (`stack` con `character_item_id` o `instance` con UUID), cantidad, contexto opcional de Zone y una UUID idempotente. Los campos de Item, precio, saldo, rareza y refinamiento enviados por el cliente están prohibidos.

El servidor bloquea Character, replay existente, Shop/localización, fuente, equipamiento, Item y Wallet; crea `ShopSale`, retira mediante la autoridad de inventario o marca la instancia `sold`, acredita Wallet y completa el snapshot dentro de una transacción. Una creación responde 201 y un replay idéntico reconstruido desde `metadata` responde 200; conflictos usan 409, fuentes o Shops inaccesibles 404, autorización 403 y reglas inválidas 422.

El snapshot versión 1 conserva entrada idempotente, catálogo del Item, instancia/origen cuando aplica, precio base y rate, saldos anterior/posterior, capacidad posterior y Zone. La pestaña pública **Vender** todavía no existe.

## Lectura autoritativa

`ShopSaleReadService` prepara por SSR el catálogo vendible sin escribir datos. Devuelve DTOs inmutables para stacks e instancias, carga Items, iconos y equipamiento en lote, excluye `sold`, conserva el refinamiento solo como información y usa las mismas autoridades de acceso, clasificación, tipos y precio que la ejecución. La cantidad bloqueada nunca se ofrece; un bloqueo parcial se presenta como advertencia.

Los precios son únicamente una vista previa: el POST vuelve a resolver fuente, Item, rate y saldo. Aún no existe pestaña Vender, controles de cantidad ni JavaScript de venta, y no se creó un GET AJAX adicional.
## Interfaz pública Comprar / Vender

La página pública de Shop recibe `saleCatalog` por SSR desde `ShopReadService`; no consulta inventario desde Blade ni mediante un endpoint GET. La pestaña Vender solo existe cuando `shopCanBuy` es verdadero y presenta stacks, instancias, cantidades disponibles, bloqueos, refinamiento y precios de vista previa ya preparados por el dominio.

`shop-sale.js` controla filtros locales, el modal independiente y el POST JSON a `characters.shops.sales.store`. El payload contiene únicamente la fuente, su referencia, cantidad, zona opcional e `idempotency_key`; nunca envía precio, total, saldo, rareza ni refinamiento como autoridad. Una operación conserva su UUID durante errores de red y reintentos, y una selección nueva crea otro UUID.

Tras una respuesta 201 o un replay 200, la interfaz usa el estado final del servidor para actualizar oro, capacidad y cantidad restante, o retirar solamente la entrada vendida. Los errores 404, 409, 422, respuestas inesperadas y fallos de red se muestran sin actualización optimista. El bundle se genera y publica mediante `npm run build`.

Limitaciones actuales: no hay recompra, venta masiva, mercado entre jugadores ni categorías de Items aceptadas por cada Shop.
