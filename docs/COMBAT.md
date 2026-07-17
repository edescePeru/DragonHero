# Motor de combate

El combatiente del personaje recibe estadísticas efectivas desde `CharacterStatsCalculator`. `attack_speed_bonus` aumenta la prioridad de ataque según el orden por velocidad de cada ronda; no representa todavía ataques por segundo.

El núcleo representa participantes inmutables agrupados explícitamente en `players` y `enemies`. Cada `CombatParticipantState` conserva estadísticas, vida, bando y posición. Los identifiers deben ser únicos; el futuro constructor de encuentros asignará identificadores de aparición como `grey_wolf:1` y `grey_wolf:2`.

`CombatState` mantiene participantes, ronda, `actionOrder` y `currentActionIndex`; el siguiente actor se deriva de esos datos. `CombatTurnOrder` es la única fuente de orden: velocidad descendente, players primero en empate, posición y finalmente identifier. Los muertos pendientes se omiten sin RNG y la ronda siguiente reconstruye el orden con supervivientes.

`CombatCommand` expresa una intención y objetivo explícito. `CombatActionResolver` valida bandos contrarios y resuelve exactamente una acción. El acierto usa `clamp(75 + accuracy - evasion, 5, 95)` y puntos base. El crítico solo se tira tras acertar. El daño aplica reducción y crítico sin redondeos intermedios, con `round()` final y mínimo 1.

`CombatSimulator::simulate()` conserva el contrato histórico 1 contra 1. `simulateEncounter()` automatiza un player contra 1–10 enemies: el player ataca al enemigo vivo de menor posición y cada enemigo ataca al player. Diez participantes por bando es un límite operativo centralizado, no una regla permanente.

La victoria se evalúa por bando. Por compatibilidad, `character_victory` significa victoria de players y `monster_victory` victoria de enemies. `CombatEncounterResult` expone además el bando ganador. El núcleo admite estados N contra M para futuras parties, raids y PvP, pero no implementa orquestación automática multi-player, persistencia, habilidades, WebSockets ni recompensas.
