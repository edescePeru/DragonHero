# Cacerías manuales

Una cacería valida la zona, selecciona un monstruo por pesos relativos de `zone_monsters`, calcula un combate aislado y persiste únicamente su resumen. Los pesos no son porcentajes. Se filtran estado y nivel, incluyendo máximos nullable.

El orden global de locks es Character → Zone → ZoneMonster elegibles → Monster seleccionado. La simulación ocurre provisionalmente dentro de la transacción porque está acotada a 100 rondas y no realiza I/O. Si crece, deberá revisarse el flujo de snapshots y persistencia.

Los nombres se guardan como snapshots históricos. No se persiste el log completo. En esta fase no cambia `current_health` y varias cacerías pueden partir de la misma vida persistida. Tampoco existen experiencia, oro, loot o inventario como recompensas.

Cada POST crea una cacería nueva y usa PRG. El botón se deshabilita visualmente, pero eso no es garantía autoritativa: un doble POST puede crear dos filas hasta incorporar energía, cooldown o una clave de operación.
