# Equipamiento básico

El equipamiento utiliza exclusivamente `ItemInstance`. `CharacterEquipment` representa la ocupación actual de cada slot; el historial permanece en `ItemInstanceEvent`.

## Slots y compatibilidad

Los slots iniciales son `weapon_main`, `helmet`, `armor`, `gloves`, `boots`, `necklace`, `ring_left` y `ring_right`. `CharacterEquipmentSlot` define su orden, etiqueta y `EquipmentType` compatible. Un Item equipable exige `item_type = equipment`, `equipment_type` válido, `is_stackable = false` y `max_stack = 1`; cualquier otra combinación se rechaza.

## Estados y capacidad

`available` ocupa un slot de mochila. `equipped` requiere una fila `character_equipment` y no ocupa mochila. La policy de instancias es la única autoridad del conteo. Equipar libera un slot, reemplazar tiene variación neta cero y desequipar requiere un slot físico libre. La reserva de cacería no impide desequipar cuando existe ese espacio físico.

## Operaciones y trazabilidad

Las rutas POST reciben únicamente UUID completo y slot controlado. El servicio bloquea Character, inventario agregado, instancias, equipo e Items en ese orden. El reemplazo genera `unequipped` y `equipped` con el mismo UUID v4. Una operación idempotente no escribe, no cambia timestamps y no genera UUID.

Un Item inactivo no puede entrar al equipo. Si fue desactivado mientras estaba equipado, puede permanecer y siempre puede salir. Los eventos son append-only por contrato de aplicación, no una protección criptográfica frente a escritura SQL directa.

## Alcance MVP

Este incremento administra propiedad, slots, estado y capacidad. No aplica estadísticas ni modifica combate. Refinamiento, evolución, requisitos, offhand, armas de dos manos, sets, durabilidad, comercio y drag-and-drop permanecen fuera de alcance.

`CharacterEquipmentTestingSeeder` es opcional, idempotente y está restringido a entorno `testing` y bases con sufijo `_testing`; no pertenece al catálogo oficial ni se ejecuta desde `DatabaseSeeder`.
