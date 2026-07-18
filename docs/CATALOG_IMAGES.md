# Imágenes optimizadas de catálogo

## Arquitectura

Items y Monsters reutilizan `media_assets`; no existen columnas ni tablas paralelas. `CatalogImageService` procesa archivos con GD y delega la referencia polimórfica a `MediaAssetService`. Item usa `icon` y Monster usa `portrait`. Los controladores y Blade no procesan archivos ni consultan Storage.

## Entrada y procesamiento

Se aceptan PNG, JPEG y WebP reales. Fileinfo y `getimagesize` deben coincidir antes de decodificar. Límites: 5 MB, 32×32 mínimo, 4096×4096 máximo y 16.777.216 píxeles. JPEG admite EXIF 1, 3, 6 y 8. GD crea un lienzo cuadrado transparente, centra sin recortar ni deformar y genera WebP 64, 128 y 256 con calidad 82.

## Persistencia

El disk es `public`. Cada reemplazo usa UUID nuevo:

```text
game-assets/items/YYYY/MM/{uuid}/
game-assets/monsters/YYYY/MM/{uuid}/
```

Contiene `original.{png|jpg|webp}`, `64.webp`, `128.webp` y `256.webp`. El `MediaAsset.path` apunta siempre a `128.webp`. Metadata versión 1 contiene `catalog_type`, `catalog_image_root`, paths y pesos de variantes, y metadata del original. El original se conserva para regeneración, pero ninguna presentación del jugador expone su URL.

El directorio nuevo se verifica antes de actualizar la referencia. Si falla procesamiento o persistencia se elimina lo nuevo y se conserva lo anterior. Tras el commit se limpia el directorio anterior; un fallo de limpieza genera warning sin invalidar la imagen nueva. Eliminar imagen desvincula primero el recurso y después limpia archivos. Otros tipos multimedia no se modifican.

## Presentación y compatibilidad

`CatalogImageView` acepta únicamente `url(64)`, `url(128)` y `url(256)`. Los read services/ViewModels entregan las URLs ya resueltas a Blade. Un asset antiguo sin metadata usa su `disk/path` canónico para cualquier tamaño; metadata incompleta, variante ausente, disk inválido o archivo perdido terminan en el fallback correspondiente sin escrituras automáticas.

Los fallbacks versionados están en `public/assets/images/catalog`. Listados usan 64 con lazy loading; formularios y paneles usan 128; fichas futuras pueden usar 256. Character Overview consume 64 en slots y 128 en el panel de detalle.

Los directorios UUID son inmutables. El servidor puede aplicar `Cache-Control: public, max-age=31536000, immutable`. No se usan query strings por `filemtime`.

## Requisitos y futuro

Requiere PHP 7.3 con GD, WebP, Fileinfo y EXIF. El procesamiento actual es síncrono por el bajo volumen administrativo. Carga masiva, colas, regeneración, CDN y limpieza diferida quedan fuera de esta fase.
