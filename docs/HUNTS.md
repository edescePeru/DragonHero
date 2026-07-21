# Cacerías manuales

Cada `Hunt` representa un encuentro completo de un personaje contra uno o varios enemigos. `Hunt` conserva la cabecera y el resultado general; cada `HuntEnemy` conserva un participante enemigo y sus snapshots históricos.

## Configuración y selección

`zone_encounter_sizes` es la fuente de verdad por zona para la cantidad de enemigos. `enemy_count` indica cuántos aparecen y `weight` su peso relativo. El dominio admite pesos que no sumen 100; únicamente el panel administrativo exige 100 para presentar probabilidades enteras claras y administra las cantidades 1, 2 y 3. Ejemplos: `100/0/0`, `0/100/0`, `60/30/10` y `0/50/50`.

El máximo administrable actual es 3, mientras que el límite técnico permanece en `CombatLimits::MAX_PARTICIPANTS_PER_SIDE` (10). La selección de monstruos usa reemplazo, por lo que una sola especie elegible puede ocupar varias posiciones con identifiers independientes. El formulario usa `100/0/0` como fallback visual para zonas heredadas sin filas, pero Hunting no aplica fallback silencioso: toda zona cazable necesita al menos una configuración activa válida. Los pesos de `zone_monsters` deciden cuáles aparecen y son independientes de los pesos de cantidad.

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

## Separación de modos en la interfaz

La pantalla de `HuntingSession` está reservada a la cacería automática: inicia el tick, presenta encuentros, historial, recompensas y controles de detención, y no contiene controles ni endpoints de combate manual. La elección entre cacería automática y combate manual se realiza únicamente desde la Zone o el mapa.

El polling automático detiene los reintentos ante respuestas HTTP. Un 401 sigue el flujo de autenticación; 403/404, 409, 422 y errores del servidor detienen el polling y presentan un reintento manual. Los fallos de red usan solo dos reintentos con backoff de 2 y 5 segundos antes de requerir intervención del jugador.
