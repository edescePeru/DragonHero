# Presentación visual de rarezas

`item_rarities` es la autoridad de presentación para instancias únicas. Conserva color, opacidad en puntos base (`0..10000`), ancho de borde, color/opacidad del glow interno, blur y spread. Los colores se almacenan como `#RRGGBB` en mayúsculas y nunca como CSS libre.

`ItemRarityVisualStyleResolver` valida y transforma la configuración a un DTO inmutable. Este expone únicamente siete variables CSS permitidas. Ante datos heredados inválidos usa `visual_style` como fallback semántico; no consulta la base.

La clase `.item-rarity-visual` consume las variables preparadas en servidor tanto en inventario como en equipamiento del Overview y combate manual. El nombre de rareza, el nombre del objeto y la referencia pública continúan visibles: el color no es la única señal.

En Character Overview, la regla específica de los slots con `.item-rarity-visual` se aplica después del borde base. Así, el borde y el glow configurados administrativamente prevalecen sin `!important`. Las clases heredadas `overview-inventory-slot--rarity-*` quedan como fallback semántico y no sobrescriben un nodo que ya dispone del contrato dinámico.

Las recompensas UNIQUE pendientes conservan la identidad de rareza y se resumen por Item + rareza; dos rarezas del mismo Item nunca se fusionan. La capa de presentación carga todas las rarezas requeridas en una consulta agrupada y aplica el mismo `ItemRarityVisualStyleResolver` usado por el resto de la interfaz. Los registros históricos sin `item_rarity_id` no vuelven a tirar rareza ni asumen `common`.

La carga inicial de la cacería y sus actualizaciones dinámicas consumen las mismas siete variables CSS preparadas en servidor. Tras reclamar, el inventario usa la rareza persistida en `ItemInstance`, además de su referencia pública y refinamiento, sin reroll. Los Items STACKABLE continúan agrupados por Item y no reciben rareza visual.

El equipamiento de la cacería automática reutiliza `CharacterEquipmentSummaryService`, incluido su snapshot de bonuses efectivos. Por ello muestra la misma rareza persistida, referencia pública y siete variables visuales que Character Overview, sin mantener una traducción paralela dentro de Hunting.

El administrador ofrece selector y texto HEX sincronizados, porcentajes humanos exactos y previews claro/oscuro. Al guardar, el cambio aparece en la siguiente carga sin modificar instancias, recalcular estadísticas ni recompilar assets.

Las rarezas de stacks, animaciones, partículas, colores por refinamiento y efectos de nivel +15 quedan fuera de este incremento.
