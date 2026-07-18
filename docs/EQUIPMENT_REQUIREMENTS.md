# Requisitos de equipamiento

`EquipmentEligibilityService` es la única autoridad contextual para propiedad, estado, catálogo, slot, nivel y clase. `required_level = 1` es neutral y la ausencia de clases asociadas permite cualquier clase. Un personaje excepcionalmente sin clase solo puede equipar Items sin restricción de clase.

La clase inicial es `adventurer` (`Aventurero`). No aporta estadísticas ni habilidades. Las clases inactivas no pueden asociarse nuevamente, pero una asociación histórica se conserva y sigue siendo válida para un personaje que ya pertenezca a ella.

Orden de locks de equipamiento: Character, CharacterClass, inventario agregado, ItemInstances, CharacterEquipment, Items y finalmente asociaciones/clases permitidas; siempre ordenados por ID dentro de cada grupo. Las lecturas no usan locks.

Política provisional deliberada: un objeto ya equipado que deja de cumplir continúa aportando estadísticas y se marca como incompatibilidad pendiente. No se desequipa ni se excluye durante una lectura. Un futuro cambio autoritativo de clase o reducción de nivel deberá resolverlo transaccionalmente.

Los requisitos solo restringen equipar. No filtran loot, claim, inventario ni refinamiento. `EquipmentRequirementsTestingSeeder` crea clases e Items visuales provisionales, no usuarios ni personajes demo.
