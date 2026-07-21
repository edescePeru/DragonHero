# Motor de combate

El combatiente del personaje recibe estadísticas efectivas desde `CharacterStatsCalculator`. `attack_speed_bonus` aumenta la prioridad de ataque según el orden por velocidad de cada ronda; no representa todavía ataques por segundo.

## Combate manual por turnos

La primera fase persistente utiliza `CombatSession` y `CombatParticipant`. Al crearla desde una `HuntingSession` conectada, congela las estadísticas efectivas del Character y de cada Monster, inicia cada participante con su vida máxima y calcula la iniciativa mediante `CombatTurnOrder`. `current_hp` permanece fuera del snapshot porque es estado mutable.

`active_slot` contiene el ID del Character mientras el combate no sea terminal y tiene una restricción única compatible con MySQL 5.7. Los futuros estados terminales deben liberar el slot asignándole `null`. Una petición repetida para la misma HuntingSession devuelve el combate activo; otro contexto recibe un conflicto controlado.

Si la iniciativa pertenece primero a un Monster, la sesión queda `active` solo durante la resolución transaccional de sus turnos automáticos; no se altera el orden para favorecer artificialmente al Character. Mientras exista `active_slot`, el tick conectado conserva el heartbeat pero no crea otro Hunt.

Cada Monster genera una recompensa inmutable una sola vez al pasar de `alive` a `defeated`. La victoria intenta entregar atómicamente Items, oro y experiencia; una falta funcional de capacidad conserva el combate `won` y deja sus recompensas `pending_claim`, sin revivir enemigos ni repetir tiradas. El claim posterior está acotado al `CombatSession` y no utiliza el claim global del Character.

### Acciones y eventos persistentes

`combat_action_requests` registra cada intención humana mediante la clave única `(combat_session_id, client_action_id)`. `lock_version` es la versión pública del estado: comienza en 1 al crear el combate y aumenta exactamente una vez por acción humana aceptada, incluyendo todos los turnos automáticos que esa acción desencadena. Una repetición procesada devuelve `response_payload` y nunca vuelve a consumir RNG.

`combat_events` es un registro inmutable con `sequence` ascendente por combate. `basic_attack` conserva un payload versionado con `targets[]`; las tiradas se guardan para auditoría, pero el Resource público las elimina. La lectura admite `after_sequence`.

El avance reconstruye `CombatState` desde snapshots y estado mutable, y delega cada golpe a `CombatActionResolver`. Los Monsters seleccionan determinísticamente un participante player vivo y actúan consecutivamente hasta que vuelve el turno humano o el combate termina. Cada nueva ronda reconstruye su orden mediante `CombatTurnOrder`.

`won`, `lost`, `abandoned` y `expired` liberan `active_slot` y limpian el participante actual. Una victoria entregada registra `rewards_granted_at`; derrota, abandono y expiración forfeitean mediante una misma autoridad cualquier recompensa todavía pendiente.

### Ciclo de vida, expiración y reanudación

El abandono es una transición explícita mediante `POST .../abandon`; nunca se dispara por cerrar, refrescar o abandonar la página. `combat_lifecycle_requests` aporta idempotencia mediante `(combat_session_id, client_request_id)`. Un abandono aceptado aumenta `lock_version` exactamente una vez, actualiza `last_action_at`, conserva todo el historial y emite `combat_abandoned`.

Solo `active` y `waiting_player` expiran. El límite se configura con `MANUAL_COMBAT_EXPIRATION_MINUTES` y tiene fallback seguro de 30 minutos. La actividad autoritativa es la creación, una acción humana aceptada y sus turnos automáticos, una recuperación automática excepcional y el abandono. GET, refresh, replay idempotente, payload inválido y conflicto de versión no renuevan actividad.

La expiración es perezosa y se comprueba bajo locks al leer, actuar, abandonar, reclamar o crear otro combate. Usa `last_action_at` y, para registros históricos sin valor, `started_at`. La transición libera el slot, aumenta `lock_version` una vez, forfeitea recompensas y emite `combat_expired`. Un combate `won` con `pending_claim` nunca expira.

El flujo normal resuelve todos los turnos de Monster en la transacción que inicia o procesa una acción. Por eso no existe un endpoint `/resume`. Al reabrir un dato histórico que quedó `active`, la lectura ejecuta internamente `ManualCombatTurnService`, salvo que exista una `CombatActionRequest` en `processing`; se detiene en `waiting_player` o en un estado terminal y versiona el cambio una sola vez.

Mientras `active_slot` está ocupado, HuntingSession mantiene heartbeat sin crear un Hunt automático. Al alcanzar `won`, `lost`, `abandoned` o `expired`, la misma transacción libera el slot y detiene la HuntingSession vinculada explícitamente por `hunting_session_id`; registra una razón `manual_combat_*`, fija `stopped_at` y limpia `next_encounter_at`. Esa sesión no se reutiliza como cacería automática: el jugador debe iniciar una nueva. `HuntingSessionService::start()` recupera de forma perezosa las sesiones antiguas que quedaron `running` por este defecto únicamente cuando tienen un combate terminal vinculado y ningún combate activo vinculado.

El núcleo representa participantes inmutables agrupados explícitamente en `players` y `enemies`. Cada `CombatParticipantState` conserva estadísticas, vida, bando y posición. Los identifiers deben ser únicos; el futuro constructor de encuentros asignará identificadores de aparición como `grey_wolf:1` y `grey_wolf:2`.

`CombatState` mantiene participantes, ronda, `actionOrder` y `currentActionIndex`; el siguiente actor se deriva de esos datos. `CombatTurnOrder` es la única fuente de orden: velocidad descendente, players primero en empate, posición y finalmente identifier. Los muertos pendientes se omiten sin RNG y la ronda siguiente reconstruye el orden con supervivientes.

`CombatCommand` expresa una intención y objetivo explícito. `CombatActionResolver` valida bandos contrarios y resuelve exactamente una acción. El acierto usa `clamp(75 + accuracy - evasion, 5, 95)` y puntos base. El crítico solo se tira tras acertar. El daño aplica reducción y crítico sin redondeos intermedios, con `round()` final y mínimo 1.

`CombatSimulator::simulate()` conserva el contrato histórico 1 contra 1. `simulateEncounter()` automatiza un player contra 1–10 enemies: el player ataca al enemigo vivo de menor posición y cada enemigo ataca al player. Diez participantes por bando es un límite operativo centralizado, no una regla permanente.

La victoria se evalúa por bando. Por compatibilidad, `character_victory` significa victoria de players y `monster_victory` victoria de enemies. `CombatEncounterResult` expone además el bando ganador. El núcleo admite estados N contra M para futuras parties, raids y PvP, pero no implementa orquestación automática multi-player, persistencia, habilidades, WebSockets ni recompensas.
## Interfaz web del combate manual

La pantalla `characters.manual-combats.play` es una capa de presentación sobre los contratos JSON autoritativos. Blade prepara únicamente estructura, URLs seguras y recursos visuales; JavaScript consulta el estado, envía `basic_attack`, claim y abandono, y nunca calcula daño, turnos, resultados, recompensas ni expiración.

La UI conserva `client_action_id` cuando una respuesta de red es incierta, actualiza el estado ante conflictos de `lock_version`, deduplica eventos mediante `sequence` y usa `after_sequence` para lecturas incrementales. Recargar o cerrar la pestaña no abandona el combate. La expiración mostrada es informativa y siempre se confirma con el servidor.

Matriz manual del Incremento 5: creación y reapertura, selección de objetivos vivos, doble clic, dos pestañas y conflicto 409, rewards provisionales/granted/pending_claim/forfeited, claim, abandono, expiración, refresh y tamaños desktop/tablet/móvil. No se incorporan Phaser, Canvas, WebSockets, `beforeunload` ni `sendBeacon`.

## Entrada separada al combate manual

El combate manual se inicia exclusivamente desde una Zone o su panel de mapa mediante `characters.manual-combats.zones.store`. El orquestador reutiliza una `CombatSession` activa o detiene primero la `HuntingSession` automática y crea un contexto nuevo antes de delegar en `ManualCombatCreationService`. La respuesta redirige directamente a `characters.manual-combats.play`; la vista no contiene URL de tick automático ni ofrece un cambio de modo.

Al terminar, abandonar o expirar, `ManualCombatPresentationService` deriva desde la Zone una `returnUrl` autoritativa hacia el mapa de su World/Region. Volver al mapa no inicia ningún modo. Una sesión manual activa conserva el slot único del Character y el tick automático mantiene su protección existente sin generar Hunts.
