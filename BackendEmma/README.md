# Emma Accesorios – Backend (Symfony 3.4)

API REST en JSON que usa el frontend Angular. Reemplaza al antiguo `index.php`.

## Qué cambió respecto de la versión anterior

| Antes | Ahora |
|---|---|
| Un único `index.php` con SQL armado concatenando strings (inyección SQL) | Symfony 3.4 + Doctrine DBAL con **consultas preparadas** |
| Contraseñas guardadas en texto plano | Hash **bcrypt** (cost 12) |
| "JWT" firmado con una clave aleatoria distinta en cada login (no se podía verificar) | JWT HS256 firmado con `JWT_SECRET`, con vencimiento (8 h) y validado en cada petición |
| La API no exigía estar logueado | Todas las rutas `/api/*` (salvo `/api/login`) requieren `Authorization: Bearer <token>` |
| Sin protección contra fuerza bruta | Bloqueo de cuenta 15 min tras 5 intentos fallidos + límite de 20 intentos por IP |
| CORS abierto a cualquier sitio (`*`) | Solo los orígenes de `CORS_ALLOW_ORIGIN` |
| Credenciales de la base en el código | Variables de entorno (`.env`) |
| Stock descontado aunque la venta fallara | Ventas, compras y canjes en **transacciones**; no se vende sin stock |
| Precios tomados del navegador | Precios y ganancias calculados en el servidor |

## Requisitos

- PHP 7.1 a 8.3 con `pdo_mysql` (probado con PHP 8.3)
- MariaDB 10.4+ o MySQL 5.7+
- [Composer](https://getcomposer.org/)

> ⚠️ **Symfony 3.4 ya no recibe parches de seguridad** (fin de soporte: noviembre 2021) y
> `composer audit` informa vulnerabilidades conocidas. La mayoría afecta componentes que esta API no
> usa (Twig, Mailer, X509), y para la que sí aplica (CVE-2025-64500) hay una mitigación en
> `PathInfoSubscriber`. Aun así, para producción conviene migrar a Symfony 6.4/7.x LTS: los
> controladores y repositorios están escritos de forma que el paso sea directo.

## Instalación

```bash
cd BackendEmma
composer install
cp app/config/parameters.yml.dist app/config/parameters.yml
cp .env.dist .env          # completar JWT_SECRET y CORS_ALLOW_ORIGIN
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # genera un JWT_SECRET
```

La conexión a MySQL se configura en `app/config/parameters.yml` (copiado del archivo
`parameters.yml.dist`, que no contiene credenciales reales). El archivo local se ignora en Git.
`DATABASE_URL` ya no se usa; las variables JWT y CORS siguen configurándose en `.env`.

### Base de datos

- **Instalación nueva:** ejecutar `sql/schema.sql`.
- **Base existente (versión anterior):** hacer backup y ejecutar **una sola vez** `sql/migracion_v1_a_v2.sql`.
  Las contraseñas viejas (en texto plano) se invalidan: regenerarlas con
  `php bin/console app:usuario:password email@dominio.com`.

> No ejecutes `doctrine:schema:update`: el esquema se maneja con los archivos de `sql/`.

Se recomienda que la aplicación use un usuario de MySQL propio con permisos mínimos:

```sql
CREATE USER 'emma_app'@'localhost' IDENTIFIED BY 'una-clave-larga-y-aleatoria';
GRANT SELECT, INSERT, UPDATE, DELETE ON emmaaccesorios.* TO 'emma_app'@'localhost';
```

### Usuarios

```bash
# Crea un usuario con una contraseña aleatoria de 20 caracteres (se muestra una sola vez)
php bin/console app:usuario:crear admin@emmaaccesorios.com "Emma" --admin

# Usar una contraseña propia (mínimo 12 caracteres)
php bin/console app:usuario:crear vendedor@emmaaccesorios.com "Vendedor" --preguntar

# Resetear la contraseña (también desbloquea la cuenta)
php bin/console app:usuario:password admin@emmaaccesorios.com
```

Si preferís hacerlo por SQL, ver `sql/crear_usuario.sql`.

### Levantar el servidor

Desarrollo (sin Apache):

```bash
SYMFONY_ENV=dev php -S localhost:8000 -t web web/app.php
```

Con XAMPP: copiar `BackendEmma` dentro de `htdocs`; la API queda en
`http://localhost/BackendEmma/web/api` (el `.htaccess` de `web/` ya está preparado, hace falta `mod_rewrite`).
En producción el *DocumentRoot* debería apuntar a `web/` para que `.env`, `app/` y `vendor/` no sean accesibles.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/login` | `{email, password}` → `{token, expiraEn, usuario}` |
| GET | `/api/me` | Usuario actual |
| POST | `/api/me/password` | `{actual, nueva}` |
| GET | `/api/dashboard` | Resumen del mes |
| GET/POST | `/api/productos` | Listar (`?q=`) / crear `{nombre, stock, precio, coste}` |
| GET/PUT/DELETE | `/api/productos/{id}` | Ver / editar / baja lógica |
| GET/POST | `/api/insumos` | `{nombre, stock, precio, descuentoCanje}` |
| GET/PUT/DELETE | `/api/insumos/{id}` | |
| GET/POST | `/api/clientes` | `{nombre, ciudadId, telefono}` |
| GET/PUT/DELETE | `/api/clientes/{id}` | |
| GET/POST | `/api/proveedores` | `{nombre, ciudadId, telefono}` |
| PUT/DELETE | `/api/proveedores/{id}` | |
| GET/POST | `/api/ciudades` | `{nombre, provincia}` |
| PUT/DELETE | `/api/ciudades/{id}` | |
| GET | `/api/provincias` | |
| GET/POST | `/api/compras` | `{proveedorId, insumoId, cantidad, costo, fecha?}` (suma stock del insumo) |
| GET/POST | `/api/canjes` | `{proveedorId, productoId, insumoId, cantidadProducto, cantidadInsumo, descuentoProducto?, descuentoInsumo?}` |
| GET | `/api/ventas` | Tickets. Filtros: `clienteId`, `productoId`, `ciudadId`, `desde`, `hasta` (YYYY-MM-DD) |
| POST | `/api/ventas` | `{clienteId, items: [{productoId, cantidad}]}` |
| GET | `/api/ventas/{id}` | Ticket con sus renglones |

Errores: siempre JSON `{message}`; las validaciones devuelven 422 con `{message, errors: {campo: mensaje}}`.

## Estructura

```
app/config/         configuración (seguridad, rutas, servicios)
src/AppBundle/
  Controller/       un controlador por recurso
  Repository/       todas las consultas SQL (preparadas)
  Security/         usuario, JWT, autenticador y límite de intentos
  EventSubscriber/  CORS, errores JSON, mitigaciones
  Command/          comandos de consola para gestionar usuarios
sql/                esquema, migración y query de alta de usuario
web/app.php         punto de entrada
```
