# Loot de monstruos

Las probabilidades de loot son independientes y se expresan en puntos base: 1 equivale a 0,01 % y 10000 a 100 %. No son los pesos de aparición de monstruos y no necesitan sumar 100 %. Cada entrada activa con item activo consume una tirada inclusiva de 1 a 10000, ordenada por `sort_order` e ID.

Una cantidad fija no consume otra tirada; un rango exitoso usa una tirada inclusiva entre mínimo y máximo. Los valores son cantidades agregadas y no están limitados por `max_stack`. El RNG compartido permite pruebas deterministas.

Generar no significa entregar: LootGenerator no modifica inventario, billetera, experiencia ni oro. Una integración futura enviará apilables a InventoryService y objetos únicos al futuro sistema `item_instances`. Los valores iniciales son provisionales. La esencia de dragón queda reservada para futuros jefes acompañados por dragones.
