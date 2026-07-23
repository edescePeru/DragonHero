# Balance defensivo de combate

## Autoridades

`CombatActionResolver` sigue siendo la única autoridad que resuelve golpes tanto en cacería automática como en combate manual. Delega exclusivamente la mitigación a `CombatDamageMitigationService`. La configuración se carga una vez mediante `CombatMitigationConfigProvider` y viaja como snapshot inmutable en `CombatantStats`; los snapshots manuales históricos sin estos campos usan Absorb 0 y los defaults v1.

La fórmula anterior `defense / (defense + 100)`, con cap 75 %, ya no decide daño. La reducción aplicada a un golpe nuevo es contextual:

```text
raw_defense_reduction = defense / (attack + defense)
defense_reduction = min(raw_defense_reduction, defense_cap)
damage_after_defense = incoming_damage * (1 - defense_reduction)
effective_absorb = min(equipment_absorb, absorb_cap)
damage_after_absorb = damage_after_defense * (1 - effective_absorb)
minimum_by_total_cap = incoming_damage * (1 - total_mitigation_cap)
unrounded_damage = max(damage_after_absorb, minimum_by_total_cap, minimum_damage)
final_damage = round(unrounded_damage)
```

El `incoming_damage` entra después del crítico existente. No se redondean el crítico ni etapas defensivas; se usa `round()` una sola vez al final. Un golpe acertado causa como mínimo el valor configurado y un fallo causa 0.

## Configuración

`combat_balance_settings` es una tabla tipada: cap de Defensa 7000 bp (70 %), cap de Absorb 7000 bp, cap combinado 9000 bp y daño mínimo 1. El panel usa porcentajes humanos con hasta dos decimales y versión optimista. Los combates ya persistidos no se recalculan.

`items.absorb_damage_basis_points` es explícito, entre 0 y 1000 bp por Item equipable. El total deriva únicamente de `ItemInstance` equipadas mediante `CharacterEquipmentStatsProvider`. Items inactivos ya equipados siguen la política actual de ataque/defensa y conservan sus stats. Inventario no equipado y objetos vendidos no aportan. El refinamiento no aumenta Absorb.

Ejemplos con defaults:

- Ataque 10, Defensa 5, Absorb 0: 33.33 % de reducción y 7 de daño.
- Ataque 10, Defensa 6, Absorb 0: 37.50 % y 6 de daño.
- Ataque 500, Defensa 500, Absorb 60 %: 250 tras Defensa, 100 tras Absorb.
- Defensa y Absorb al 70 % producirían 91 % combinada; el cap global conserva 10 % del daño entrante.

Son valores provisionales de balance. No se implementan rarezas automáticas, bonus +15, Pets, auras, Skills, consumibles, escudos, penetración, resistencias ni PvP. Futuras fuentes de Absorb deberán integrarse en la misma agregación, sin duplicar la fórmula.
