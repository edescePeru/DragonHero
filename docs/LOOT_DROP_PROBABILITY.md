# Probabilidad de loot

## Autoridad y unidad interna

`monster_loot_entries.drop_probability_ppm` es la autoridad actual. La unidad interna
es PPM (partes por millón):

- `1,000,000 PPM = 100 %`
- `10,000 PPM = 1 %`
- `1 PPM = 0.0001 %`

El campo histórico `drop_chance_basis_points` se conserva temporalmente para
compatibilidad de migración, queda nullable y no recibe escrituras nuevas de la
aplicación.

## Administración

El administrador introduce un porcentaje como texto decimal, entre `0` y `100`, con
un máximo de cuatro decimales. `PercentagePpmConverter` realiza la conversión sin
usar `float`, evitando notación científica, redondeos y formatos ambiguos.

La interfaz muestra tanto el porcentaje con cuatro decimales como el valor PPM
persistido.

## Generación independiente

`LootGenerator` solicita una tirada entera uniforme entre `1` y `1,000,000` mediante
`RandomNumberGenerator`. El drop ocurre cuando:

```text
roll_ppm <= drop_probability_ppm
```

Por tanto, `0 PPM` nunca cae y `1 PPM` únicamente cae con una tirada igual a `1`.
Cada entrada realiza su propia tirada y varias entradas del mismo Monster pueden caer
en un encuentro.
Los metadatos nuevos de recompensas manuales guardan
`configured_probability_ppm` y `roll_ppm` con versión `2`.
Los metadatos históricos de versión `1` conservan sus claves y unidades en basis
points; no se reinterpretan ni recalculan.

## Compatibilidad y reversión

La migración convierte exactamente cada valor histórico mediante
`basis_points * 100`. La reversión solo está permitida cuando todos los valores PPM
son divisibles entre `100`; si existe una probabilidad más fina, falla explícitamente
para impedir pérdida de precisión.

Este incremento no implementa roll de rareza ni mapeo de rarezas. La probabilidad de
obtener un Item y la rareza de una futura instancia continúan siendo conceptos
separados.
