# Emma Accesorios

Sistema de gestión (ventas, compras, canjes, stock y clientes) para Emma Accesorios.

| Carpeta | Contenido |
|---|---|
| [`BackendEmma/`](BackendEmma/README.md) | API REST en Symfony 3.4 + MariaDB/MySQL |
| [`AccesoriosEmma/`](AccesoriosEmma/README.md) | Frontend Angular 20 con Angular Material (Material Design 3) |

## Puesta en marcha rápida

```bash
# 1. Base de datos (instalación nueva)
mysql -u root < BackendEmma/sql/schema.sql

# 2. Backend
cd BackendEmma
composer install
cp .env.dist .env                         # completar DATABASE_URL y JWT_SECRET
php bin/console app:usuario:crear admin@emmaaccesorios.com "Emma" --admin
SYMFONY_ENV=dev php -S localhost:8000 -t web web/app.php

# 3. Frontend (en otra terminal)
cd AccesoriosEmma
npm install
npm start                                 # http://localhost:4200
```

Si ya tenías datos cargados con la versión anterior, usá `BackendEmma/sql/migracion_v1_a_v2.sql`
en lugar de `schema.sql` (ver el README del backend).
