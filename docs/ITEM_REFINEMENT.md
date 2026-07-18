# Refinamiento de ItemInstance v1

El refinamiento mejora una `ItemInstance` de equipamiento un nivel por operación, entre `+0` y `+15`. En v1 todas las reglas válidas tienen 10000 puntos base (100 %) y `failure_behavior=keep_level`. Los valores son balance provisional.

## Autoridad y atomicidad

`ItemRefinementService` recarga regla, oro y materiales desde MySQL dentro de una transacción. El token cifrado contiene solo identidad y precondiciones de concurrencia; nunca costes ni resultados. Oro, materiales, actualización y evento se confirman o revierten juntos. El orden global es Character, ItemInstance, configuración, CharacterWallet y CharacterItem ordenados por `item_id`.

La repetición exacta de un token reconstruye `RefinementResult` desde el evento append-only. Un token distinto emitido para un nivel anterior se rechaza como obsoleto. Los materiales se consumen de `character_items`; una fila se elimina al quedar su cantidad total en cero.

## Bonificación estadística configurable

`refinement_stat_modifiers` define un porcentaje acumulado por nivel (`stat_increase_basis_points`). El nivel actual usa exclusivamente su propia fila: un objeto `+3` no suma `+1`, `+2` y `+3`. `10000` puntos base equivalen a 100 % y el rango administrativo provisional es `0..100000`.

`ItemRefinementStatCalculator` es la única autoridad de la fórmula. Las estadísticas enteras usan aritmética entera y crítico/velocidad se convierten a centésimas antes de calcular. Una configuración ausente o inactiva aplica cero y se informa como faltante sin interrumpir el juego. Los stats base siguen viviendo en `Item`; `ItemInstance` solo aporta el nivel.

El seeder visual configura provisionalmente `+1..+15` con incrementos lineales de 10 % para validar arquitectura. Son valores de desarrollo pendientes de balance.

## Fallo seguro y probabilidad

Cada regla admite entre `1` y `10000` puntos base. El servidor genera exactamente una tirada entera entre `1` y `10000`; hay éxito cuando `roll <= success_chance_basis_points`. Un fallo `keep_level` es un intento válido: consume el oro y todos los materiales, conserva nivel, UUID, Item, estado y slot, y registra `refinement_failed`.

La idempotencia depende de `operation_uuid`. Repetir el mismo token reconstruye éxito o fallo sin consumir ni tirar nuevamente. Dos tokens distintos del mismo nivel son intentos independientes: después de un fallo el segundo sigue siendo válido; después de un éxito queda obsoleto porque el nivel cambió. Los eventos históricos de éxito permanecen compatibles y no se reescriben.

## Límites de v1

El nivel todavía no modifica estadísticas, combate ni vida. Refinar una instancia equipada conserva estado, slot, UUID, origen y relación de equipamiento. No se generan eventos de equipar o desequipar. El catálogo administrativo no elimina reglas: las desactiva. `ItemRefinementTestingSeeder` es explícito, idempotente, exclusivo de una base `_testing` y no entrega recursos a personajes.
