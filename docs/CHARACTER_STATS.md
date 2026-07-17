# Estadísticas efectivas del personaje

`CharacterStatsCalculator` es la única autoridad que interpreta estadísticas base, equipamiento y valores efectivos. Los controladores, Blade, `HuntService` y el motor de combate consumen sus DTOs y no repiten fórmulas.

Los bonus viven en `Item`, no en `ItemInstance`. Una instancia aporta los bonus actuales de su Item únicamente mientras ocupa un slot confirmado de `character_equipment`. `refinement_level` se conserva en la trazabilidad, pero todavía no modifica estadísticas.

Las estadísticas efectivas primarias son la suma de base y equipamiento. La reducción de daño se deriva después desde la defensa efectiva mediante `DamageReductionCalculator`, y el poder se calcula una sola vez sobre los valores efectivos sin redondeos intermedios. Los DTOs aplican el redondeo al exponer los resultados.

`attack_speed_bonus` se suma a la velocidad base actual de `1.00`. Con la semántica actual del motor, una velocidad mayor aumenta la prioridad de ataque al ordenar los turnos de cada ronda; no representa todavía tiempo real ni ataques por segundo.

Equipar o desequipar nunca modifica `current_health`. Al iniciar un combate se utiliza `min(current_health, effective_max_health)`, por lo que aumentar vida máxima no cura y retirarla no escribe daño en el personaje.

Cada Hunt guarda `character_stats_snapshot` con `schema_version`, bloques `base`, `equipment`, `effective` y `equipment_sources`. Cada fuente identifica slot, UUID de instancia, Item, nombre, refinamiento y bonus aplicados. Un snapshot histórico no se recalcula. Una HuntingSession no congela equipamiento: cada nuevo Hunt toma el equipamiento confirmado en ese momento.

Los bonus de los fixtures de `CharacterEquipmentTestingSeeder` son provisionales y no constituyen balance definitivo.

## Equipo y refinamiento

La composición efectiva es `base del personaje + equipo base + refinamiento`. `CharacterEquipmentStatsProvider` prepara las capas y `CharacterStatsCalculator` consume el total una sola vez. El bonus de `attack_speed` aumenta la frecuencia de ataque conforme a la semántica actual del motor.

Equipar, desequipar o cambiar configuración nunca escribe `current_health`. Los snapshots nuevos usan `schema_version = 2`, conservan las claves previas y añaden `equipment_base`, `refinement` y fuentes ampliadas. Se aceptan snapshots v1 y v2 sin reescribir historia. Cada Hunt nuevo captura la configuración vigente; una HuntingSession no congela los Hunts futuros.
