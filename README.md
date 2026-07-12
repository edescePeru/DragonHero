# A4gamesDH

A4gamesDH es un RPG web medieval en desarrollo, construido con Laravel 8 y PHP 7.3. La primera interfaz utiliza un dashboard administrativo basado en la plantilla InApp.

La visión inicial del juego está documentada en `docs/GAME_DESIGN.md` y las reglas permanentes de desarrollo en `AGENTS.md`.

## Requisitos

- PHP 7.3 compatible con las extensiones requeridas por Laravel.
- Composer 2.
- MySQL 5.7 o posterior.
- Node.js y npm.

## Instalación local

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Configura en `.env` la conexión local a MySQL. No incluyas ese archivo ni credenciales reales en Git.

El virtual host debe apuntar al directorio `public/` del proyecto.

## Assets de la plantilla

`src/` contiene las fuentes originales de la plantilla y Vite genera `dist/` mediante `npm run build`. Laravel sirve actualmente los assets integrados desde `public/assets/`; por ello `dist/` no se versiona.

## Base de datos para pruebas

Las pruebas automatizadas usan exclusivamente la base MySQL `A4gamesDH_testing`. La clase base de pruebas bloquea inmediatamente la ejecución si `APP_ENV` no es `testing` o si el nombre de la base no termina en `_testing`.

Crea la base local:

```sql
CREATE DATABASE A4gamesDH_testing
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Copia `.env.testing.example` como `.env.testing` y añade credenciales solo en el archivo local ignorado por Git. La clave fija configurada por PHPUnit es exclusiva para pruebas y no debe reutilizarse en otros entornos.

Ejecuta las migraciones y pruebas únicamente contra el entorno de testing:

```bash
php artisan migrate --env=testing
php artisan test
```

`phpunit.xml` fuerza el uso de `A4gamesDH_testing`. No ejecutes `migrate:fresh`, `db:wipe` ni pruebas automatizadas contra `A4gamesDH`.

## Plantilla administrativa

La interfaz inicial deriva de InApp Inventory Admin Dashboard, creada por CodesCandy y distribuida por ThemeWagon bajo licencia MIT. Consulta `LICENSE` para más información.
