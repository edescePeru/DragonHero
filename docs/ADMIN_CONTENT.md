# Admin Content v1

Panel interno para crear y balancear Items, Monsters, Zones, relaciones `zone_monsters` y `monster_loot_entries`. No incluye usuarios, roles, métricas, economía, herramientas GM ni auditoría administrativa.

El acceso requiere sesión y que el correo normalizado esté incluido en `DRAGONHERO_ADMIN_EMAILS`, separado por comas. La lista queda fuera del repositorio. `EnsureContentAdministrator` aísla esta decisión para poder sustituirla por RBAC en el futuro.

Las escrituras pasan por Form Requests y `ContentAdminService`, que reutiliza los validadores del catálogo, clasificación, equipamiento, bonus y loot. Los controladores no calculan reglas y Blade recibe relaciones cargadas previamente.

Los endpoints DELETE de Items, Monsters y Zones nunca borran filas: cambian el estado a `inactive`. Esto preserva Hunts, rewards, inventarios, equipamiento y eventos históricos. Retirar un Monster de una Zone sí elimina exclusivamente la configuración mutable de la tabla pivote.

Zones mantienen la jerarquía `World → Region → Zone`; se persiste `region_id` y no existe `world_id` redundante. Loot almacena puntos base. La conversión a porcentaje es solo visual: `10000 = 100%` y `7000 = 70%`.
