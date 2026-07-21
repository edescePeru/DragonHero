# Recursos multimedia

## Alcance

`media_assets` almacena referencias reutilizables a imágenes y sprites de personajes, monstruos, objetos, zonas, regiones y mundos. La base de datos guarda el disco, una ruta relativa normalizada y metadata opcional; nunca guarda binarios, Base64, URLs absolutas ni archivos físicos.

La separación arquitectónica es explícita:

- Dominio: estadísticas, reglas y combate.
- Media: archivos, sprites e imágenes.
- Presentación: decide qué recurso mostrar.

Las imágenes no forman parte de las fórmulas ni de la lógica del juego. Los DTO del motor de combate no conocen `MediaAsset`.

## Escrituras

`MediaAssetService` es la única puerta recomendada para escrituras. Aunque `MediaAsset` es un modelo Eloquent público, un controlador no debe crearlo directamente, cambiar `is_primary` ni ejecutar `delete()` cuando exista una regla de negocio. Toda escritura futura debe pasar por el servicio para conservar validación, transacciones y exclusividad del recurso principal por tipo.

El servicio solo admite aliases definidos por el morph map (`character`, `monster`, `item`, `zone`, `region`, `world`). Nunca debe persistirse un nombre de clase como `App\\Models\\Monster` en `mediable_type`.

Las rutas se almacenan relativas, sin espacios exteriores, null bytes, esquemas, rutas absolutas ni segmentos `..`. Los separadores `\\` aceptados en una ruta relativa se normalizan a `/`. `metadata` es un array libre; dimensiones de frames, FPS y otras reglas de sprites corresponderán a un servicio específico futuro.

## Consultas y rendimiento

`HasMediaAssets` ofrece consultas explícitas: `mediaAssets()`, `mediaAssetsOfType($type)` y `primaryMediaAsset($type)`. Los dos últimos devuelven un query builder y no ejecutan consultas ocultas. No se agregan accessors de imagen ni URLs a `$appends`.

Para listados se debe usar eager loading cuando la presentación necesite recursos:

```php
Monster::with(['mediaAssets' => function ($query) {
    $query->where('asset_type', MediaAssetType::ICON);
}])->get();
```

`url()` es explícito y genera la URL usando `disk` y `path` solamente cuando la capa consumidora la solicita.

## Eliminación

El trait registra un evento Eloquent `deleting` que elimina las filas `media_assets` del modelo. No elimina los archivos físicos del disco: el ciclo de vida y posible reutilización de archivos requieren una política futura.

Las eliminaciones masivas como `Monster::where(...)->delete()` no ejecutan eventos Eloquent y, por tanto, no limpian estas referencias. No se implementa todavía un Observer global; los modelos deben eliminarse individualmente mediante Eloquent o mediante un servicio futuro.

Las factories solo generan referencias de prueba (`public`, `test/example.webp`) y nunca crean archivos físicos.

## Consumo desde Blade

Las vistas nunca consultan recursos multimedia. Los controladores preparan eager loading filtrado y los componentes anónimos consumen exclusivamente la colección `mediaAssets` ya cargada. Una relación no cargada no dispara consultas: se muestra el mismo placeholder accesible que cuando la relación está cargada pero no contiene un recurso aplicable.

Los componentes disponibles son:

```blade
<x-media.icon :model="$item" alt="Icono del objeto" :width="40" :height="40" />
<x-media.portrait :model="$monster" alt="Retrato del monstruo" :width="96" :height="96" />
<x-media.sprite :model="$monster" :type="\App\Domain\Media\MediaAssetType::SPRITE_ATTACK" alt="Ataque" />
```

`x-media.asset` selecciona de forma determinista por tipo exacto, recurso principal, `sort_order` e ID. Los wrappers solo establecen el tipo. Ningún componente llama relaciones, `load`, `first` sobre queries ni otros métodos que accedan a la base de datos.

El inventario conserva sus DTO `InventoryEntry`; el controlador crea por separado un mapa de modelos Item con sus iconos ya cargados. Si falta un Item, el DTO sigue mostrándose con placeholder y sin consulta individual.

El componente sprite representa por ahora el sprite sheet como una imagen estática. La lectura de metadata, reproducción de frames y animación se implementarán en una fase posterior.
# Banner de la Home

`home_card_banner` es un recurso primario 1200 × 400 exclusivo de `GameHomeCard`. No se mezcla con iconos, retratos, cuerpos ni capas equipadas. Véase `docs/GAME_HOME_CARDS.md`.
