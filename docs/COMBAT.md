# Motor de combate aislado

El motor trabaja exclusivamente con snapshots inmutables y no persiste resultados. Una ronda ordena combatientes por `attackSpeed` (el personaje gana empates), ejecuta el primer ataque y permite respuesta solo si el segundo sigue vivo.

La probabilidad provisional de acierto es `clamp(75 + accuracyRate - evasionRate, 5, 95)`. Las comparaciones usan puntos base enteros de 1 a 10000 y el éxito incluye igualdad. El crítico se tira solo tras acertar y limita su probabilidad entre 0 y 100 %.

El daño usa `attack × (1 - damageReductionRate / 100) × criticalDamageMultiplier`. No se redondean valores intermedios: `round()` se aplica una sola vez al resultado final, se convierte a entero y un acierto causa al menos 1. Un fallo causa 0. La reducción compartida es `(defense / (defense + 100)) × 100`, con máximo 75 %.

El combate termina en victoria del personaje, victoria del monstruo o empate tras 100 rondas. CombatResult contiene exclusivamente CombatRound y cada ronda exclusivamente CombatAction, mediante copias defensivas. Todas las fórmulas son provisionales y quedan pendientes de balance. No se modifican vida persistida, experiencia, oro, inventario ni loot.
