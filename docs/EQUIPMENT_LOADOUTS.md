# Loadouts de equipamiento

DragonHero tiene nueve slots reales: `main_hand`, `off_hand`, `helmet`, `armor`, `gloves`, `boots`, `necklace`, `ring_left` y `ring_right`. `pet` es únicamente un placeholder de presentación y no participa en persistencia, endpoints, estadísticas ni snapshots.

Los Items de mano estructurados usan `hand_requirement` (`one_hand`, `two_hand`, `off_hand_only`) y una familia controlada. Espada, hacha, daga, arco, bastón, lanza y varita son armas; escudo, foco, grimorio y orbe son soportes de mano secundaria. Un Item histórico `weapon` con ambos campos nulos conserva compatibilidad `legacy_main_hand_only`.

`EquipmentEligibilityService` valida el Item individual. `EquipmentLoadoutValidator` valida la combinación de ambas manos y consulta dual wield exclusivamente mediante `CharacterClassCapabilityService`. Dual wield V1 exige dos armas `one_hand`, una principal ya equipada y `can_dual_wield=true`.

Un arma `two_hand` con `off_hand` ocupado produce un conflicto que requiere confirmación. El request confirmado solo envía `confirm_conflicts=true`; el servidor relee y bloquea el loadout, desplaza `off_hand`, reemplaza `main_hand` y registra los eventos con un mismo UUID dentro de una transacción. La dirección inversa se rechaza.

La migración cambia únicamente filas actuales `weapon_main` a `main_hand`. Snapshots y eventos históricos append-only no se reescriben; `CharacterEquipmentSlot::normalizeHistorical()` permite presentar `weapon_main` con la etiqueta actual. Nuevos eventos y snapshots usan los códigos nuevos.

Esta fase no implementa composición visual por capas, mascotas reales, habilidades ni una matriz avanzada de familias.
