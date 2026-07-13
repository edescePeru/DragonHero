# Sesiones de cacería conectada

Una `HuntingSession` agrupa múltiples Hunts en una zona. El modo `connected` solo avanza cuando el navegador envía ticks cortos: no existen peticiones abiertas, `sleep`, workers ni procesamiento de fondo. Cada tick procesa como máximo un encuentro y reutiliza `HuntService` como autoridad.

El heartbeat se sugiere cada 10 segundos y vence a los 30 segundos. La expiración es perezosa: cerrar el navegador no consume CPU; el próximo tick o intento de inicio detiene la sesión abandonada. El polling es adaptativo: mantiene heartbeat como máximo cada 10 segundos y se aproxima al vencimiento de `next_encounter_at` para respetar el cooldown de victoria de 3 segundos.

Una victoria reinicia resultados no ganados y programa 3 segundos. El primer resultado no ganado programa 30 segundos, el segundo 60 y el tercero detiene la sesión. Un draw incrementa su contador propio, pero cuenta como resultado no ganado para esta protección. `current_health` no se persiste entre encuentros y las estadísticas se recalculan en cada Hunt.

Esta solución es propia del MVP. Con más usuarios, cada navegador genera solicitudes PHP breves y no hay trabajo cuando deja de llamar. En el futuro puede migrarse a WebSockets, workers, colas o planificación centralizada sin cambiar las reglas persistidas de la sesión. No existe progreso offline, procesamiento retroactivo ni recompensas en esta fase.
