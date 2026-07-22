# Tiendas

`Shop` es la autoridad de tienda; `Npc` representa al comerciante visual y puede reutilizarse posteriormente en diálogos u otras interacciones. Una Shop referencia un NPC y se publica mediante `shop_locations`. La localización es polimórfica con aliases obligatorios; en v1 solo está habilitado `zone`, aunque una misma Shop puede asociarse a más de una Zone.

Las ofertas referencian exclusivamente Items existentes. `category` prepara agrupaciones de armas, armaduras, consumibles, materiales, recetas y evento. `visibility` determina si una oferta puede presentarse; `status`, ventanas, stock, Item y requisitos determinan si es comprable. Por ello una oferta visible puede mostrar Comprar deshabilitado. Inicio es inclusivo, fin exclusivo y las fechas se interpretan en UTC.

`quantity` es la cantidad entregada por paquete y `gold_price` es el precio entero del paquete completo. Un stock ilimitado tiene `stock_limit` y `stock_remaining` nulos. Un stock limitado exige ambos valores, con `0 <= stock_remaining <= stock_limit`; el stock cuenta paquetes. Las compras conservarán snapshots de Item, cantidad y oro, UUID idempotente global y referencias protegidas con `RESTRICT`.

NPC y Shop no almacenan paths ni URLs. Usan `MediaAsset`: NPC admite `portrait`; Shop admite `banner` y `background`. Toda escritura multimedia futura debe pasar por `MediaAssetService`; `MediaAsset::url()` continúa siendo la única construcción explícita de URL.

## Administración de contenido v1

El mantenedor interno vive en `/admin/content/npcs` y `/admin/content/shops`, protegido por la autorización compartida basada en `DRAGONHERO_ADMIN_EMAILS`. Permite administrar NPC, Shop, localizaciones Zone, ofertas y sus estados sin crear rutas de borrado físico. Este alcance es exclusivamente una herramienta de creación y balance de contenido.

Las escrituras pasan por los servicios `NpcAdminService`, `ShopAdminService` y `ShopOfferAdminService`. Los controladores solo coordinan Form Requests y respuestas. La búsqueda AJAX devuelve como máximo 20 Items activos y coherentes; la selección definitiva vuelve a validarse dentro de la escritura. En esta versión son vendibles `material`, `dragon_material`, `consumable` y `equipment`; `quest`, `key` y `currency_item` quedan excluidos. La categoría `recipes` queda preparada, pero no admite Items hasta que exista un tipo semántico de receta.

La administración multimedia reutiliza `MediaAssetService`. `ShopMediaService` valida PNG/JPG/WebP reales de hasta 5 MB, crea paths UUID únicos, reemplaza un primario por tipo y limpia el archivo nuevo ante rollback o el anterior solo después del éxito. El retrato del NPC y el banner/background de Shop se reemplazan o eliminan independientemente desde el mismo formulario.

El mantenedor administrativo no ejecuta compras ni modifica Wallet o inventario; esas operaciones pertenecen exclusivamente al motor transaccional descrito a continuación. Todavía no existe navegación pública de Shops.

## Motor transaccional de compra

`ShopPurchaseService` es la única autoridad para comprar una oferta. El cliente envía exclusivamente un UUID idempotente; precio, cantidad, Item, stock, límites y estados se releen de la oferta persistida bajo locks. Una compra entrega el paquete completo o revierte ShopPurchase, ledger, Wallet, inventario, ItemInstances, eventos y stock.

Orden de locks: Character; replay por clave; Shop; ShopOffer; Item; CharacterWallet; CharacterItem; ItemInstances; grants de capacidad; conteo de compras; ShopPurchase. Character serializa los recursos y límites del jugador, ShopOffer serializa el stock entre jugadores y el UNIQUE de `shop_purchases.idempotency_key` protege claves todavía inexistentes. Una colisión concurrente se relee después del rollback: mismo contexto devuelve replay y otro contexto devuelve conflicto.

`metadata` contiene un snapshot de versión 1 con código/nombre del Item, saldos, stock, contador personal y capacidad posterior. Cantidad, oro, Item y fecha permanecen además en columnas. El replay se reconstruye desde el snapshot sin revalidar contenido vigente ni repetir efectos, tolerando campos visuales opcionales ausentes.

Los apilables se entregan mediante `InventoryService::addManyLocked()`. Los únicos se crean mediante `ItemInstanceService::createFromShopPurchaseLocked()`, con origen `shop_purchase`, evento `created_from_shop_purchase`, refinamiento 0 y estado `available`. El proyecto ya usa UUID v5 deterministas como convención general de procedencia; la entrada de compra es `shop-purchase-item-instance:v1:{purchase_id}:{unit_index}`.

El endpoint técnico `POST /characters/{character}/shops/{shop}/offers/{offer}/purchases` devuelve 201 para compra nueva, 200 para replay, 403 para ownership, 404 para relación Shop/Offer incorrecta, 409 para disponibilidad/stock/límite/conflicto y 422 para request, nivel, capacidad, saldo o Item inválido.

## Catálogo público v1

`ZoneShopCatalogService` prepara las tarjetas de tiendas localizadas sin cargar sus catálogos completos. `ShopReadService` valida personaje, disponibilidad y contexto de Zone, carga solo ofertas visibles, agrupa compras y prepara saldo, capacidad, medios y estados sin consultas desde Blade. Una Shop localizada exige un `zone` válido; una Shop sin localizaciones es global y puede enlazarse desde una `GameHomeCard` de destino `shop`. El `character_id` nunca se persiste en la tarjeta.

El navegador filtra el catálogo ya autorizado. La compra envía exclusivamente `idempotency_key`: conserva la clave ante un error de red incierto y crea otra tras una respuesta definitiva. Oro, stock, límites, precio, capacidad y entrega siguen siendo decisión exclusiva de `ShopPurchaseService`.

El JavaScript público se genera siempre con `npm run build`: Vite escribe en `dist/assets` y el publicador obligatorio sincroniza esa salida con `public/assets` sin borrar recursos públicos ajenos. Laravel sirve `public/assets/js/main.js` y su `filemtime` funciona como versión de caché. La presencia de `data-shop-catalog` en el bundle público confirma que la interfaz de compra quedó publicada.
