# Catálogo del mundo

## Jerarquía

El catálogo sigue `World → Region → Zone`. Cada nivel usa un `code` técnico estable en snake_case y un `name` visible que puede cambiar sin romper relaciones. La estructura admite nuevos mundos, regiones y zonas sin cambios arquitectónicos.

## Visibilidad

Los estados son `active`, `inactive` y `hidden`. El jugador solo puede consultar contenido `active`; `inactive` no es visible ni utilizable y `hidden` queda reservado para administración, testing o contenido futuro. El filtrado ocurre en los servicios de dominio, no en Blade.

## Zonas y conexiones

Los tipos iniciales son town, field, forest, mine, dungeon_entrance y special. No hay mapas ni coordenadas.

Una conexión representa una dirección. Una fila bidireccional funciona en ambos sentidos sin duplicarse. Una conexión entrante no bidireccional solo está disponible cuando existe otra fila explícita en sentido inverso; no se infieren rutas adicionales.

`required_item_id` usa `nullOnDelete`: eliminar físicamente el objeto requerido convertiría la conexión en una conexión sin requisito. En esta fase no existe borrado físico de items desde la interfaz.

## Monstruos y pesos

Los monstruos se reutilizan entre zonas mediante `zone_monsters`. `weight` es un peso relativo de configuración, no un porcentaje y no necesita sumar 100. La interfaz lo muestra solo como información de desarrollo. No existe selección aleatoria ni encuentro funcional.

## Objetos

Items es un catálogo general sin inventario, equipo, precios ni comercio. Los objetos apilables requieren `max_stack` positivo; los no apilables usan `max_stack = null`.

## Contenido inicial

- Eldoria
  - Reino de Valtheria
    - Aldea del Alba
    - Bosque de Roblegris
    - Minas Abandonadas

Las estadísticas de monstruos, pesos y niveles actuales son provisionales para pruebas y no representan balance definitivo.

## Fuera de alcance

Viaje real, encuentros, combate, loot, inventario, dungeons funcionales, profesiones, crafteo, dragones, cartas, clima y presentación gráfica quedan fuera de esta fase.
