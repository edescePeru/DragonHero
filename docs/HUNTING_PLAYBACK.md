# Reproducción autoritativa de HuntingSession

El servidor resuelve cada Hunt completamente en una transacción y persiste las acciones estructuradas. No se guardan textos traducidos. Cada evento conserva impacto, tiradas, crítico, daño, cadena de vida y su `playback_offset_ms` y `playback_duration_ms`; el navegador no recalcula reglas ni tiempos.

`resolved_at` es el instante del servidor en que el resultado quedó resuelto y preparado para persistencia. Los Hunts históricos anteriores conservan `resolved_at = completed_at`, velocidad x1, duración cero y ningún evento inventado.

La velocidad proviene de `HuntingPlaybackSpeedProvider`. El valor inicial es 10000 (x1). Los segmentos usan aritmética entera, mínimo 100 ms y un máximo técnico total de 30 minutos. El siguiente encuentro espera el máximo entre cooldown y reproducción, redondeando los milisegundos hacia arriba a segundos.

Saltar animación solo completa el DOM local. No cambia `next_encounter_at`, velocidad, rewards ni economía. El polling continúa como heartbeat y el servidor impide Hunts anticipados.
