# FARIO

Aplicación Laravel + Vue (Vite) con base de datos MySQL.

## Requisitos

- PHP 8.3+ y Composer
- Node.js 20+ y npm
- MySQL o MariaDB (por ejemplo XAMPP)

## Instalación

```bash
git clone <url-del-repo>
cd FARIO-Laravel

composer install
npm install

cp .env.example .env
php artisan key:generate
```

### Base de datos

El respaldo completo (estructura + datos) está en `database/fario.sql`. Crea la base `fario` al importarlo:

```bash
mysql -u root < database/fario.sql
```

O desde phpMyAdmin: **Importar** → selecciona `database/fario.sql`.

Si tu MySQL no usa el puerto 3306 o tiene contraseña, ajusta `DB_PORT` / `DB_PASSWORD` en `.env`.

## Ejecutar

En dos terminales:

```bash
php artisan serve
npm run dev
```

Abre http://localhost:8000
