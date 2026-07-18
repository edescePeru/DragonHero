# World Maps

`World`, `Region` y `Zone` siguen siendo dominio del juego. `WorldMap` representa una imagen contextual y `WorldMapArea` solo enlaza geometría visual con una acción validada. No contiene lógica de Hunt, loot o combate.

## Contextos y estados

Cada mapa pertenece exactamente a un World o a una Region. Un mapa default debe estar activo y tener una imagen accesible. La unicidad default se mantiene transaccionalmente. Desactivar un default retira la marca sin elegir otro automáticamente.

Las áreas usan `draft`, `active` e `inactive`. Draft e inactive no aparecen al jugador. Una activa fuera de su ventana permanece visible, deshabilitada y con mensaje autoritativo.

## Acciones

Operativas: `zone`, `map`, `world`, `region`, `internal_route`, `info`. `seasonal_event` y `special_shop` están reservadas y no pueden activarse. Las rutas internas se eligen mediante claves de `config/world_maps.php`; parámetros ligados al personaje se resuelven desde el usuario autenticado.

Una zona abre un panel y nunca inicia una Hunt por click. Los formularios POST continúan usando CSRF, rutas y servicios existentes.

## Geometría y presentación

Los puntos se guardan normalizados, con 3–100 vértices, seis decimales, área mínima y sin auto-intersección. La superposición entre áreas está permitida. El orden visual es `z_index`, `sort_order`, `id`.

## Imágenes y concurrencia

`WorldMapImageService` es la autoridad de almacenamiento. Usa el disk configurable, MIME y dimensiones reales, nombres aleatorios y URLs de Storage. En reemplazo se guarda primero el nuevo archivo; si falla DB se elimina solo el nuevo. Tras commit se intenta retirar el anterior y un fallo se registra sin revertir la referencia vigente. Cambios relevantes de aspect ratio exigen confirmación y nunca alteran polígonos.

Mapa y área tienen versiones independientes. Las escrituras bloquean primero el mapa padre; después el área actual y filas hermanas necesarias. Las lecturas no usan locks. Editar un área no incrementa la versión de otras áreas ni la del mapa.

## Assets y testing

Las fuentes viven en `src/assets`, Vite compila hacia `public/assets` y el layout continúa sin `@vite`. `WorldMapTestingSeeder` es exclusivo de `_testing`, idempotente y copia un fixture técnico versionado al disk configurado. No crea credenciales.
# Requisito operativo del disk público

Los mapas guardan únicamente el nombre del disk y una ruta relativa normalizada. Cuando `WORLD_MAP_DISK=public`, cada entorno local o productivo debe exponer `storage/app/public` mediante `public/storage`:

```bash
php artisan storage:link
```

La existencia física (`file_exists_on_disk`) y la accesibilidad pública (`public_url_available`) son conceptos distintos. `WorldMapImageService` prepara ambos datos. Las lecturas normales no realizan una petición HTTP por mapa; el despliegue debe verificar el enlace y el editor aplica un fallback seguro si el navegador no puede cargar la URL.
