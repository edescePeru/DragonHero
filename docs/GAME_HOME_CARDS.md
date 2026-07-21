# Cards configurables de la página inicial

`GameHomeCard` representa un acceso administrable de la Home. Persiste nombre, código slug único, tipo y valor de destino, orden, estado y apertura en nueva pestaña. Las activas se ordenan por `sort_order` y después por `id`.

Los destinos admitidos son `route`, `active_character_overview`, `character_selector` y `external_url`. `GameHomeCardRouteCatalog` mantiene la lista blanca de rutas GET del jugador y define si requieren el Character activo. `ActiveCharacterContext` obtiene siempre el personaje desde `users.active_character_id`; nunca se acepta un ID administrativo. Sin personaje activo se usa el selector o la creación. Las URLs externas aceptan solo HTTP/HTTPS.

El banner es un `MediaAssetType::HOME_CARD_BANNER` primario asociado morfológicamente. Debe ser PNG, JPEG o WebP decodificable, medir exactamente 1200 × 400 y pesar como máximo 5 MB. Se conserva el original sin variantes. La ausencia del banner usa un fallback CSS 3:1 con el nombre, sin crear MediaAsset falso.

El panel `content.admin` permite crear, editar, ordenar, activar, desactivar, eliminar y gestionar el banner. El reemplazo conserva el anterior ante un fallo de persistencia y limpia archivos huérfanos. Eliminar un banner conserva la card; eliminar una card limpia únicamente sus assets.

`GameHomeCardSeeder` garantiza por código tres accesos: Cambiar de PJ (10), Mi PJ (20) y Mundo (30). Mundo usa `world-maps.index`, es decir `/maps`. No se siembra Inventario, aunque el administrador puede crearlo con `characters.inventory.index`. El seeder no sobrescribe nombre, orden, estado, nueva pestaña o banner personalizados.

`characters.show` continúa como ficha detallada, pero la navegación principal usa `characters.overview`. Forja, Market, Misiones, PvP y Mazmorras son posibles cards futuras; sus funcionalidades no forman parte de este incremento.
