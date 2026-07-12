# Motor de combate

`CombatantStats` es el snapshot inicial inmutable. `CombatState` conserva vidas, ronda, orden inicial, siguiente actor y estado. `CombatCommand` expresa únicamente una intención `basic_attack`. `CombatActionResolver` valida y resuelve exactamente una acción, devolviendo `CombatStepResult`. Como todos son inmutables, pueden compartirse referencias sin clonarlas.

El acierto es `clamp(75 + accuracy - evasion, 5, 95)` y usa puntos base inclusivos. El crítico solo se tira tras acertar. El daño es `attack × (1 - reduction / 100) × criticalMultiplier`, sin redondeos intermedios, con `round()` final y mínimo 1 en aciertos.

`CombatSimulator` es el orquestador PvE: crea comandos básicos, delega cada fórmula al resolver, agrupa acciones en rondas y declara empate tras 100 rondas. Mayor velocidad comienza y el personaje gana el empate. Una futura sesión PvP podrá persistir/versionar CombatState y enviar comandos al mismo resolver; tiempo real nunca significa confiar en resultados del cliente.

No se implementan sesiones persistidas, PvP, habilidades, cooldowns, WebSockets ni recompensas.
