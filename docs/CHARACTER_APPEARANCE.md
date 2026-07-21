# Apariencia estática del personaje

La composición usa un canvas editorial fijo de **512 × 768 (2:3)**. `CharacterAppearanceService` prepara la base y las capas; Blade solo las dibuja. La base (orden 10) procede de `CharacterTemplate.base_visual` o del fallback vertical. Sin `body_type` se conserva la base y se omite el equipamiento visual.

Los iconos siguen siendo `MediaAssetType::ICON` y mantienen variantes cuadradas. Una capa equipada usa `MediaAssetType::EQUIPMENT_LAYER`, conserva el PNG/WebP original y no se recorta, escala, centra ni convierte. PNG exige canal alfa; en WebP, PHP/GD confirma la decodificación y la transparencia queda como contrato editorial.

`item_visual_assets` es la autoridad de la asociación. Es única por `(item_id, body_type, visual_slot)` y por `media_asset_id`. Los body types proceden de `CharacterTemplate`; todas las plantillas que compartan uno deben usar el mismo canvas, origen y alineación.

El canal se elige por el slot realmente equipado: necklace se traduce a `pendant`; anillos y armas usan su lado real. El orden fijo es: base_body 10, pendant 20, ring_left 30, ring_right 40, helmet 50, gloves 60, boots 70, armor 80, off_hand 90 y main_hand 100.

El reemplazo valida y almacena el archivo nuevo, cambia la asociación en transacción y solo después elimina el asset/archivo anterior. Si falla la transacción se compensa eliminando el archivo nuevo. La eliminación afecta solo la combinación seleccionada.

Quedan fuera: animaciones, sprites, múltiples capas front/back, mantenedor de orden e integración con Hunting.
