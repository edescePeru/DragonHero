# Roll global de rareza

Solo los objetos únicos obtenidos de loot de monstruos realizan este roll. Los apilables no tienen rareza de instancia.

La configuración singleton usa PPM enteros y suma exactamente `1 000 000`: common 949000, rare 49000, mythic 1950 y legendary 50. Tras aprobar el drop del ítem, `LootGenerator` realiza un único roll global. Si la rareza obtenida no está permitida, baja a la permitida inferior más cercana; si no existe, utiliza la mínima permitida. No se normalizan probabilidades.

La recompensa persiste el identificador, snapshots y metadata del roll antes del claim. El claim nunca vuelve a sortear. Las recompensas históricas sin rareza usan una única rareza permitida o common cuando esté permitida; una configuración múltiple sin common falla explícitamente.

Antes de desplegar en una base con datos, diagnosticar recompensas únicas históricas pendientes con varias rarezas permitidas y sin common. El resultado requerido es cero; cualquier fila exige remediación explícita. No ejecutar diagnósticos ni migraciones de producción desde la suite automatizada.
