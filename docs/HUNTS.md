# Cacerías manuales

Cada `Hunt` representa un encuentro completo de un personaje contra uno o varios enemigos. `Hunt` conserva la cabecera y el resultado general; cada `HuntEnemy` conserva un participante enemigo y sus snapshots históricos.

## Configuración y selección

`zone_encounter_sizes` define cantidades posibles por zona mediante pesos relativos. Los pesos no son porcentajes ni necesitan sumar 100. Toda zona cazable requiere configuración explícita y el selector no aplica fallback silencioso. El límite operativo actual es `CombatLimits::MAX_PARTICIPANTS_PER_SIDE` (10), no un máximo permanente de diseño.

El selector de cantidad consume una tirada RNG incluso cuando existe una única configuración, manteniendo una secuencia determinista documentada. Después, `WeightedMonsterSelector` carga una vez los spawns y Monsters activos elegibles y selecciona N veces con reemplazo sobre la misma colección. El mismo Monster puede aparecer varias veces.

Cada aparición obtiene posición consecutiva e identifier `monster:{monster_id}:{position}`. Ese identifier correlaciona `EncounterEnemy`, `CombatantStats`, `CombatParticipantResult` y `HuntEnemy`; nunca se correlacionan repetidos solo por Monster o posición.

## Persistencia

La cabecera y todos los enemigos se guardan en una única transacción. `enemy_count` es una desnormalización controlada que debe coincidir con los hijos, sus posiciones deben ser `1..N` y sus identifiers deben ser únicos. `alive` y `defeated` se derivan exclusivamente de `final_health`.

La FK desde `HuntEnemy` hacia Monster usa RESTRICT: los monstruos históricos se desactivan y no se eliminan físicamente. Eliminar un Hunt elimina sus enemigos. Las zonas con configuración también usan RESTRICT; deben desactivarse o eliminar primero su configuración.

La migración convierte Hunts históricos con Query Builder, verifica el backfill y elimina las columnas singulares. El rollback solo es posible sin pérdida mientras todos los Hunts tengan exactamente un enemigo; aborta antes de alterar el esquema si existen encuentros múltiples.

## Snapshot de estadísticas

Cada Hunt conserva un snapshot JSON versionado con estadísticas base, bonus, valores efectivos y fuentes de equipamiento. Los Hunts históricos no se recalculan. Una HuntingSession toma un snapshot nuevo por encuentro y no congela el equipo de toda la sesión.

## Resolución

`HuntService` usa `CombatSimulator::simulateEncounter()`. Victoria significa todos los enemigos derrotados; derrota significa personaje derrotado; draw significa límite de rondas. En una derrota pueden coexistir enemigos vivos y derrotados.

La simulación no modifica `current_health` persistido y no entrega experiencia, oro, loot ni objetos. No persiste acciones completas ni recursos multimedia.

`Hunt` no es una futura `HuntingSession`: una sesión coordinará múltiples encuentros, tiempos, derrotas consecutivas y ejecución conectada u offline en otro incremento.
