# A4gamesDH — visión inicial del juego

> Estado: documento conceptual inicial. No define valores finales de balance ni constituye una especificación completa de implementación.

El catálogo jerárquico inicial del mundo se documenta en `docs/WORLD_CATALOG.md`.

## Concepto general

A4gamesDH es un RPG web medieval de gestión y progresión. El jugador administra un personaje, desarrolla sus capacidades y decide cómo emplear tiempo y recursos en actividades como cacerías, combates, dungeons, profesiones y crafteo.

La primera experiencia se presenta como un dashboard o ERP: información clara, decisiones mediante formularios y resultados explicados con registros textuales. Los gráficos complejos, sprites y animaciones no son necesarios en esta etapa.

## Pilares del juego

- **Progresión persistente:** las decisiones fortalecen al personaje, su equipo, sus profesiones y su colección.
- **Gestión estratégica:** tiempo, recursos, riesgo y oportunidades deben administrarse con intención.
- **Resultados transparentes:** el jugador recibe registros comprensibles sobre acciones, costes y recompensas.
- **Economía segura:** todo activo tiene trazabilidad y cualquier cambio económico se resuelve en el servidor.
- **Contenido extensible:** las mecánicas deben admitir nuevas zonas, enemigos, objetos, recetas, dungeons y cartas.
- **Juego accesible:** la interfaz prioriza claridad y operación desde navegador antes que presentación audiovisual.

## Bucle principal

1. Consultar el estado del personaje y los recursos disponibles.
2. Elegir una actividad, como cacería, combate, dungeon, profesión o crafteo.
3. Confirmar requisitos y costes presentados por el servidor.
4. Esperar o resolver la actividad según sus reglas.
5. Recibir un resultado calculado por el servidor.
6. Revisar el registro de experiencia, loot, materiales, oro y otros cambios.
7. Mejorar personaje, equipo, profesiones o colección.
8. Acceder progresivamente a actividades de mayor dificultad y valor.

Los tiempos, costes, límites diarios, probabilidades y recompensas están **PENDIENTES DE BALANCE**.

## Interfaz inicial estilo ERP

La interfaz inicial estará compuesta por:

- menú lateral para navegar entre sistemas;
- tarjetas de resumen del personaje y sus recursos;
- tablas de objetos, actividades, recetas y registros;
- formularios para iniciar o confirmar acciones;
- barras de progreso para experiencia, actividades y profesiones;
- estados y alertas claramente identificables;
- historial textual de operaciones y resultados.

La interfaz muestra información y solicita acciones, pero no calcula resultados del juego. En esta fase no se requieren gráficos estadísticos, sprites ni animaciones.

## Servidor autoritativo

El servidor es la única autoridad sobre el estado del juego. Debe validar requisitos y calcular daño, duración, experiencia, loot, oro, consumo de recursos, probabilidades y resultados de crafteo.

El cliente nunca puede establecer directamente un resultado ni enviar como confiables precios, cantidades, probabilidades, daño o recompensas. MySQL mantiene el estado permanente. Las operaciones económicas deben ser atómicas, auditables e idempotentes cuando exista riesgo de repetición.

Redis podrá incorporarse más adelante para bloqueos, rate limiting, cache y colas, sin sustituir a MySQL como fuente de verdad.

## Cacerías

La implementación técnica de cacerías manuales se documenta en `docs/HUNTS.md`.

Las cacerías son actividades automáticas iniciadas por el jugador. Una cacería puede depender de la zona, los requisitos del personaje, la duración y posibles costes de entrada.

El servidor registra su inicio y determina cuándo puede resolverse. Al finalizar, valida nuevamente el estado aplicable y genera los resultados. Debe impedir cobros o recompensas duplicadas.

Duraciones, límites, dificultad, experiencia y tablas de recompensas: **PENDIENTES DE BALANCE**.

## Combate

La especificación técnica del simulador aislado se documenta en `docs/COMBAT.md`.

El combate se calcula completamente en el servidor usando el estado persistido del personaje, su equipamiento, el enemigo y las reglas vigentes. La interfaz presenta el resumen y un registro textual de los eventos relevantes.

Las fórmulas de atributos, daño, defensa, precisión, críticos, turnos, estados y derrota: **PENDIENTES DE DISEÑO Y BALANCE**.

## Loot

La configuración técnica y generación aislada se documentan en `docs/LOOT.md`.

El loot puede incluir oro, materiales, equipamiento, objetos especiales, cartas o esencias, según la actividad y su tabla de recompensas.

Cada concesión debe estar vinculada a una resolución válida y registrarse dentro de una transacción. Las tablas, rarezas, cantidades y probabilidades: **PENDIENTES DE BALANCE**.

## Inventario

Los materiales apilables usan cantidades agregadas. Armas, armaduras y otros objetos únicos usan instancias individuales con origen e historial mínimo, preparadas para refinamiento y evolución configurables futuras.

El inventario representa los objetos y recursos poseídos por el jugador. Debe permitir consultar, clasificar y utilizar objetos según reglas controladas por el servidor.

Capacidad, apilamiento, categorías, límites y reglas de destrucción o transferencia: **PENDIENTES DE DISEÑO Y BALANCE**.

## Equipamiento

El personaje podrá equipar objetos en espacios definidos. El servidor valida propiedad, requisitos, compatibilidad y cambios de estadísticas.

Espacios disponibles, requisitos, rarezas, atributos y progresión del equipo: **PENDIENTES DE DISEÑO Y BALANCE**.

## Profesiones

Las profesiones ofrecen progresión especializada y acceso a actividades, recursos o recetas. Su desarrollo debe integrarse con la economía sin producir recursos sin control.

Profesiones iniciales, experiencia, niveles, costes y beneficios: **PENDIENTES DE DISEÑO Y BALANCE**.

## Crafteo

El crafteo consume materiales y puede producir objetos mediante resultados deterministas o probabilísticos. El servidor valida la receta, reserva o consume recursos y resuelve el resultado dentro de una transacción.

Las recetas, tiempos, costes, probabilidades de éxito, calidad y consecuencias del fallo: **PENDIENTES DE BALANCE**. El cliente nunca genera el resultado aleatorio.

## Dungeons

Las dungeons son actividades estructuradas con requisitos de entrada, encuentros y recompensas. Pueden reutilizar los sistemas de combate y loot, manteniendo una instancia o progreso controlado por el servidor.

Cantidad de encuentros, intentos, costes, dificultad y recompensas: **PENDIENTES DE DISEÑO Y BALANCE**.

## Jefe normal o jefe con dragón

Una dungeon o actividad especial puede culminar en un jefe normal o en un jefe acompañado o potenciado por un dragón. Esta distinción puede modificar dificultad, reglas del encuentro y recompensas posibles.

Condiciones de aparición, poder, mecánicas especiales y recompensas: **PENDIENTES DE DISEÑO Y BALANCE**.

## Esencias

Las esencias de dragón son recursos especiales relacionados con enemigos, jefes o contenido de dragones. Podrán utilizarse posteriormente en progresión, crafteo, cartas u otros sistemas controlados.

Fuentes, tipos, usos, cantidades y rareza: **PENDIENTES DE DISEÑO Y BALANCE**.

## Cartas

El juego contempla cartas coleccionables de héroes y dragones. Las cartas podrán representar colección, progresión o efectos utilizables en sistemas futuros.

Obtención, duplicados, niveles, efectos, rarezas y relación con el personaje: **PENDIENTES DE DISEÑO Y BALANCE**.

## Economía

La economía interna administrará monedas, materiales, objetos y recursos especiales. Todo movimiento debe tener origen, destino, motivo e identificador de operación cuando corresponda.

Los saldos no pueden depender del cliente. Compras, recompensas, consumos, intercambios y crafteos deben usar validación, transacciones y mecanismos contra repetición o concurrencia. Los valores monetarios se representarán sin floats.

La economía externa queda reservada para una etapa posterior y requerirá un diseño específico de seguridad, cumplimiento, conciliación y auditoría.

Monedas, fuentes, sumideros, precios, comisiones y límites: **PENDIENTES DE DISEÑO Y BALANCE**.

## Alcance de la fase 1

La fase 1 busca demostrar una experiencia mínima, segura y verificable:

- autenticación y sesión protegida;
- estructura base del dashboard y navegación;
- perfil básico de jugador o personaje;
- una cacería automática sencilla;
- resolución básica de combate en el servidor;
- recompensa básica y registro textual del resultado;
- inventario mínimo para recibir y consultar recursos u objetos;
- controles transaccionales y pruebas automatizadas de los flujos anteriores.

La selección exacta de atributos, enemigos, objetos, recompensas, tiempos y valores permanece **PENDIENTE DE BALANCE**. Cada elemento de esta fase debe implementarse como incremento independiente y verificable.

## Sistemas fuera de la fase 1

Las cacerías manuales pueden resolver un encuentro 1 contra N; la ejecución continua y las sesiones de cacería siguen fuera de alcance. Véase [HUNTS.md](HUNTS.md).

- economía externa o transacciones con valor real;
- mercado, comercio o intercambios entre jugadores;
- PvP, gremios y funciones sociales complejas;
- múltiples dungeons y contenido avanzado de jefes;
- jefe con dragón como sistema completo;
- profesiones avanzadas;
- crafteo probabilístico avanzado;
- colección y progresión completa de cartas;
- sistemas completos de esencias de dragón;
- Redis para procesamiento distribuido, salvo que un incremento posterior lo requiera expresamente;
- clientes móviles o de escritorio;
- gráficos, sprites, audio y animaciones;
- monetización y economía externa.

Estos sistemas permanecen como visión futura y requerirán documentos de diseño e incrementos específicos antes de su implementación.
# Capacidad de inventario

La fase actual deriva slots desde cantidades agregadas y `max_stack`, contempla mejoras permanentes y eventos temporales, y reserva margen para recompensas pendientes. No existe todavía transferencia ni reclamación.

El MVP de equipamiento administra ocho slots, posesión, capacidad y trazabilidad de instancias. Las bonificaciones de estadísticas permanecen pendientes y no afectan todavía al combate.
