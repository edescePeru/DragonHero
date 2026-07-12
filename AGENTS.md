# A4gamesDH — instrucciones permanentes

## Contexto técnico actual

- RPG web medieval construido sobre Laravel 8.83.29 y PHP 7.3.33; toda implementación debe ser compatible con PHP 7.3.
- MySQL es la fuente permanente de verdad. La base local es `A4gamesDH`.
- Autenticación web por sesión ya existente: `/login`, `/registro`, `/logout`; el dashboard y sus páginas usan middleware `auth`.
- La interfaz parte de la plantilla InApp (Bootstrap 5, SCSS, Vite) en `src/`, compilada a `dist/` y adaptada a Blade en `resources/views/`.
- PHPUnit 9 está instalado. La configuración actual no aísla la base de pruebas; nunca ejecutar pruebas destructivas contra `A4gamesDH`.
- Redis aún no está instalado ni activo. Solo existe la configuración predeterminada de Laravel; no asumir disponibilidad.

## Flujo obligatorio

1. Antes de editar, inspeccionar el código relacionado y mostrar un plan breve con archivos, migraciones y pruebas previstas.
2. Implementar un incremento pequeño y verificable. No ampliar el alcance sin autorización.
3. Después del incremento, ejecutar pruebas relevantes y comunicar comandos, resultados y cualquier prueba no ejecutada.
4. No implementar personajes, combate, inventario ni otros sistemas del juego salvo petición explícita.

## Arquitectura y seguridad

- El servidor es autoritativo: el cliente nunca calcula ni decide daño, loot, experiencia, oro, costes, probabilidades, temporizadores ni resultados.
- Mantener controladores delgados. Colocar reglas del juego en servicios o clases de dominio reutilizables desde web, API, colas y futuros clientes.
- No colocar lógica de negocio en Blade, JavaScript, rutas ni modelos obesos. Las rutas solo deben conectar middleware y controladores.
- Validar y autorizar toda entrada en el servidor; no confiar en IDs, precios, cantidades, estados o marcas de tiempo enviados por el cliente.
- Toda mutación económica o de recursos debe usar transacciones de base de datos y bloqueos apropiados. Diseñar operaciones idempotentes y mantener trazabilidad/auditoría.
- Evitar floats para dinero, probabilidades acumuladas o recursos; usar enteros en la unidad mínima o decimales con precisión explícita.
- No registrar secretos, contraseñas, tokens ni datos sensibles. Mantener credenciales fuera del repositorio.
- Redis se incorporará después para locks, rate limiting, cache y colas; MySQL seguirá siendo la fuente de verdad. Definir comportamiento seguro ante fallos de Redis.

## Persistencia y cambios de esquema

- Crear migraciones reversibles; nunca editar una migración ya aplicada para cambiar el esquema.
- Añadir índices, claves foráneas, restricciones y unicidad que protejan invariantes también a nivel de base de datos.
- No ejecutar `migrate:fresh`, `db:wipe`, truncados ni borrados masivos sobre una base con datos sin autorización explícita.
- Antes de pruebas con base de datos, configurar una base exclusiva de testing o SQLite compatible y verificar activamente el entorno `testing`.

## Pruebas y calidad

- Cada sistema importante requiere pruebas automatizadas. Priorizar pruebas de dominio/unidad y pruebas feature para autenticación, autorización, validación, concurrencia y rollback.
- Las pruebas económicas deben cubrir doble envío, saldo insuficiente, límites, idempotencia y fallos parciales.
- Mantener compatibilidad con PHP 7.3: no usar arrow functions, propiedades tipadas ni sintaxis introducida en PHP 7.4+.
- No considerar terminado un incremento si las pruebas relevantes fallan; reportar fallos preexistentes por separado.

## Interfaz

- Mantener el estilo dashboard/ERP: menú lateral, tarjetas, tablas, formularios, barras de progreso y registros textuales.
- Por ahora no añadir gráficos, sprites ni animaciones.
- Blade presenta datos y JavaScript mejora la interacción; ninguno decide resultados del juego o económicos.
