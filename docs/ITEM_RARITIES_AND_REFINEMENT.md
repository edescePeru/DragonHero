# Rarezas y refinamiento de Items

## Autoridades

`Item` representa una definición única de catálogo. `ItemInstance` representa una unidad obtenida y es la autoridad de su rareza y nivel de refinamiento. El campo legado `items.rarity` se conserva temporalmente para compatibilidad de contenido agregado; no debe usarse para decidir la rareza de una instancia.

Las rarezas oficiales son exactamente:

| Código | Nombre inicial | Estilo seguro |
|---|---|---|
| `common` | Común | neutral |
| `rare` | Raro | blue |
| `mythic` | Mítico | purple |
| `legendary` | Legendario | gold |

El nombre, orden, estado, estilo seguro y modificadores aprobados viven en `item_rarities`. La relación `item_allowed_rarities` limita qué rarezas puede recibir cada Item. Editar esa relación no cambia instancias ya existentes.

La creación de instancias debe pasar por `ItemInstanceService` y `ItemInstanceRarityResolver`. Si no se especifica rareza, solo se admite `common` cuando el Item la permite. No existe roll aleatorio de rareza en este incremento.

## Modificadores de rareza

Los modificadores se agregan después del refinamiento y nunca se escalan por él.

- Armas: `rare` agrega 500 bp de precisión; `mythic`, 400 bp de crítico; `legendary`, ambos.
- Armadura y escudos: `rare` agrega 200 bp de evasión; `mythic`, 300 bp de evasión y `1.00` de velocidad; `legendary`, 400 bp de evasión, `2.00` de velocidad y 100 bp de AbsorbDamage.
- Collar y anillos pueden mostrar rareza, pero no reciben modificadores especiales ni refinamiento en esta fase.

Los estilos visuales se seleccionan desde una lista segura. La presentación siempre debe incluir también el nombre de la rareza.

## Refinamiento del stat principal

Cada Item declara `allows_refinement` y `refinement_stat` (`attack`, `defense` o `none`). Las armas refinables usan normalmente ataque; armaduras y escudos, defensa. Accesorios y objetos no equipables usan `none`.

La curva acumulada administrable continúa en `refinement_stat_modifiers`:

| Nivel | Basis points | Porcentaje |
|---:|---:|---:|
| 0 | 0 | 0 % |
| 1–10 | 100–1000 | 1–10 % |
| 11 | 2000 | 20 % |
| 12 | 3000 | 30 % |
| 13 | 4000 | 40 % |
| 14 | 4500 | 45 % |
| 15 | 5000 | 50 % |

`EffectiveItemStatsResolver` es la autoridad:

1. lee stats base;
2. aplica la curva solo al stat principal;
3. redondea una vez al entero más cercano con aritmética entera;
4. agrega los modificadores de rareza sin escalarlos.

Por tanto, ataque 100 a +15 resulta 150 y defensa 37 a +15 resulta 56. No se persisten stats efectivos.

## Presentación del inventario

`CharacterInventorySummaryService` utiliza `EffectiveItemStatsResolver` para presentar
cada `ItemInstance` UNIQUE. Su contrato expone `base_bonuses`,
`refinement_bonuses`, `rarity_bonuses` y `total_bonuses` con la misma estructura.
`bonuses` es un alias temporal de `total_bonuses` para mantener compatibilidad con
consumidores existentes.

Character Overview muestra exclusivamente los totales preparados por el servidor.
El refinamiento no escala los modificadores de rareza. Precisión, evasión y crítico
se presentan como porcentajes, mientras que AbsorbDamage se convierte visualmente
desde basis points a porcentaje; estos formatos no modifican las unidades internas
ni persisten estadísticas calculadas.

## Compatibilidad y trabajo futuro

Las migraciones asignan `common` a Items e instancias históricas sin alterar UUID, origen, propietario, estado, eventos o refinamiento. Snapshots históricos que no contienen rareza se interpretan como `common`.

Quedan fuera: crafting, probabilidades de rareza, afijos aleatorios, robo de vida, bonus automático de Absorb a +15, nuevas estadísticas de accesorios y nuevas rarezas.
# Presentación visual

La apariencia administrable de cada rareza está documentada en `docs/ITEM_RARITY_VISUALS.md`. Esta configuración no modifica estadísticas, refinamiento ni probabilidades.
