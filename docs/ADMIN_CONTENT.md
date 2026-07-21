# Admin Content v1

Panel interno para crear y balancear Items, Monsters, Zones, relaciones `zone_monsters` y `monster_loot_entries`. No incluye usuarios, roles, métricas, economía, herramientas GM ni auditoría administrativa.

El acceso requiere sesión y que el correo normalizado esté incluido en `DRAGONHERO_ADMIN_EMAILS`, separado por comas. La lista queda fuera del repositorio. `EnsureContentAdministrator` aísla esta decisión para poder sustituirla por RBAC en el futuro.

Las escrituras pasan por Form Requests y `ContentAdminService`, que reutiliza los validadores del catálogo, clasificación, equipamiento, bonus y loot. Los controladores no calculan reglas y Blade recibe relaciones cargadas previamente.

Los endpoints DELETE de Items, Monsters y Zones nunca borran filas: cambian el estado a `inactive`. Esto preserva Hunts, rewards, inventarios, equipamiento y eventos históricos. Retirar un Monster de una Zone sí elimina exclusivamente la configuración mutable de la tabla pivote.

Zones mantienen la jerarquía `World → Region → Zone`; se persiste `region_id` y no existe `world_id` redundante. Loot almacena puntos base. La conversión a porcentaje es solo visual: `10000 = 100%` y `7000 = 70%`.
# Escenario de Zone

Create y edit de Zones permiten cargar y previsualizar el fondo compartido por cacería automática y combate manual. El formulario usa `combat_background`; `remove_combat_background` solo elimina el fondo actual al guardar. Un input vacío conserva el recurso existente. La vista previa replica `background-size: cover` y `background-position: center`, y muestra nombre, resolución, tamaño y formato sin consultar desde Blade.

# Reglas de refinamiento

`/admin/content/refinement` administra transiciones y materiales. En v1 solo acepta 10000 puntos base y `keep_level`; las reglas se desactivan en vez de eliminarse para preservar la historia.

La misma pantalla administra, en una sección separada, modificadores estadísticos `+1..+15`. Permite crear, editar, activar y desactivar; no permite borrado físico. Activar revalida la fila completa y desactivar solo cambia su estado. La UI muestra puntos base, porcentaje equivalente y advertencias para niveles ausentes o inactivos. Los valores sembrados son provisionales.
# Menú de inicio

Las cards configurables se administran en `admin/content/game-home-cards` bajo el mismo middleware `content.admin`. El mantenedor controla destino, orden, visibilidad, nueva pestaña y banner. Véase `docs/GAME_HOME_CARDS.md`.
