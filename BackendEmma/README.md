# Emma Accesorios – Backend (Symfony 3.4)

API REST en JSON que usa el frontend Angular. Reemplaza al antiguo `index.php`.

## Qué cambió respecto de la versión anterior

| Antes | Ahora |
|---|---|
| Un único `index.php` con SQL armado concatenando strings (inyección SQL) | Symfony 3.4 + **Doctrine ORM** (entidades, repositorios y DQL con parámetros) |
| Contraseñas guardadas en texto plano | Hash **bcrypt** (cost 12) |
| "JWT" firmado con una clave aleatoria distinta en cada login (no se podía verificar) | JWT HS256 firmado con `JWT_SECRET`, con vencimiento (8 h) y validado en cada petición |
| La API no exigía estar logueado | Todas las rutas `/api/*` (salvo `/api/login`) requieren `Authorization: Bearer <token>` |
| Sin protección contra fuerza bruta | Bloqueo de cuenta 15 min tras 5 intentos fallidos + límite de 20 intentos por IP |
| CORS abierto a cualquier sitio (`*`) | Solo los orígenes de `CORS_ALLOW_ORIGIN` |
| Credenciales de la base en el código | Variables de entorno (`.env`) |
| Stock descontado aunque la venta fallara | Ventas, compras y canjes en **transacciones** con bloqueo de fila (`SELECT … FOR UPDATE`); no se vende sin stock |
| Precios tomados del navegador | Precios y ganancias calculados en el servidor |

## Requisitos

- PHP 7.4 (versión oficial para Symfony 3.4 + Doctrine ORM 2.7) con las extensiones `pdo_mysql`,
  `zip`, `gd`, `xml` y `mbstring` (las últimas las usa PhpSpreadsheet para los reportes en Excel).
  También funciona con PHP 8.x (probado en 8.3), aunque Doctrine ORM 2.7 no lo declara oficialmente.
- MariaDB 10.4+ o MySQL 5.7+
- [Composer](https://getcomposer.org/)

> ⚠️ **Symfony 3.4 ya no recibe parches de seguridad** (fin de soporte: noviembre 2021) y
> `composer audit` informa vulnerabilidades conocidas. La mayoría afecta componentes que esta API no
> usa (Twig, Mailer, X509), y para la que sí aplica (CVE-2025-64500) hay una mitigación en
> `PathInfoSubscriber`. Para producción conviene migrar a Symfony 6.4/7.x LTS: entidades,
> repositorios y controladores se pueden llevar casi sin cambios.

## Instalación

```bash
cd BackendEmma
composer install
cp .env.dist .env          # completar DATABASE_URL, JWT_SECRET y CORS_ALLOW_ORIGIN
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # genera un JWT_SECRET
```

### Base de datos

El esquema se define en las entidades de `src/AppBundle/Entity` (anotaciones de Doctrine).

**Instalación nueva**

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:schema:validate      # debe decir que todo está en sync
```

(`sql/schema.sql` es el mismo esquema exportado con `doctrine:schema:create --dump-sql`, por si
preferís importarlo desde phpMyAdmin.)

**Base existente**

Los scripts se corren en orden y **una sola vez** cada uno, según desde qué versión venís:

| Tu base está en… | Correr |
|---|---|
| v1 (el `index.php` original) | `migracion_v1_a_v2.sql` y después `migracion_v2_a_v3.sql` |
| v2 (Symfony, antes de timestamps/soft delete) | `migracion_v2_a_v3.sql` |

```bash
mysqldump -u root emmaaccesorios > backup_emmaaccesorios.sql     # 1. backup
mysql -u root emmaaccesorios < sql/migracion_v1_a_v2.sql         # 2a. solo si venís de v1
mysql -u root emmaaccesorios < sql/migracion_v2_a_v3.sql         # 2b. timestamps y soft delete
php bin/console doctrine:schema:update --dump-sql                # 3. revisar lo que falta
php bin/console doctrine:schema:update --force                   #    y aplicarlo
php bin/console doctrine:schema:validate                         # 4. verificar
```

Los scripts SQL convierten tipos y datos que Doctrine no puede migrar sin perder información (IDs
guardados como texto, ventas sin ticket, contraseñas en texto plano, registros ocultos con
`visibility = 0` que pasan a `deleted_at`). `schema:update` agrega las claves foráneas e índices y
**borra las columnas que ya no se usan** (imágenes y `visibility`).

**Borrados:** todas las entidades tienen `created_at`, `updated_at` y `deleted_at` (Gedmo
Timestampable / SoftDeleteable). Borrar desde la aplicación o la API solo completa `deleted_at`: el
registro deja de aparecer pero sigue en la base y en el historial. Para recuperarlo:
`UPDATE tabla SET deleted_at = NULL WHERE ...`.
Si al agregar las claves foráneas falla por datos huérfanos (por ejemplo ventas de un producto que
ya no existe), buscalos con:

```sql
SELECT * FROM venta v LEFT JOIN producto p ON p.IDProducto = v.IDProducto WHERE p.IDProducto IS NULL;
```

**Cambios futuros en las entidades:** modificá la entidad y corré
`doctrine:schema:update --dump-sql` / `--force` (siempre revisando el SQL antes, con backup).

Las contraseñas viejas quedan invalidadas: generá nuevas con
`php bin/console app:usuario:password email@dominio.com`.

En producción conviene que la aplicación use un usuario de MySQL con permisos mínimos (los comandos
`doctrine:*` de arriba se corren con un usuario administrador):

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

Desarrollo:

```bash
php bin/console server:run            # en primer plano, http://127.0.0.1:8000
php bin/console server:start          # en segundo plano (Linux/macOS); server:stop para frenarlo
```

Con XAMPP: copiar `BackendEmma` dentro de `htdocs`; la API queda en
`http://localhost/BackendEmma/web/api` (el `.htaccess` de `web/` ya está preparado, hace falta `mod_rewrite`).
En producción el *DocumentRoot* debería apuntar a `web/` para que `.env`, `app/` y `vendor/` no
sean accesibles, y `SYMFONY_ENV=prod`.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/login` | `{email, password}` → `{token, expiraEn, usuario}` |
| GET | `/api/me` | Usuario actual |
| POST | `/api/me/password` | `{actual, nueva}` |
| GET | `/api/dashboard` | Resumen del mes |
| GET/POST | `/api/productos` | Listar (`?q=`) / crear `{nombre, stock, precio, coste}` |
| GET/PUT/DELETE | `/api/productos/{id}` | Ver / editar / borrar (soft delete) |
| GET/POST | `/api/insumos` | `{nombre, stock, precio, descuentoCanje}` |
| GET/PUT/DELETE | `/api/insumos/{id}` | |
| GET/POST | `/api/clientes` | `{nombre, ciudadId, telefono}` |
| GET/PUT/DELETE | `/api/clientes/{id}` | |
| GET/POST | `/api/proveedores` | `{nombre, ciudadId, telefono}` |
| PUT/DELETE | `/api/proveedores/{id}` | |
| GET/POST | `/api/ciudades` | `{nombre, provincia}` |
| PUT/DELETE | `/api/ciudades/{id}` | |
| GET | `/api/provincias` | |
| GET/POST | `/api/compras` | `{proveedorId, fecha?, items: [{insumoId, cantidad, costo}]}` (suma stock de cada insumo) |
| GET/POST | `/api/canjes` | `{proveedorId, descuentoProducto?, descuentoInsumo?, items: [{productoId, cantidadProducto, insumoId, cantidadInsumo}]}` |
| GET | `/api/ventas` | Tickets. Filtros: `clienteId`, `productoId`, `ciudadId`, `desde`, `hasta` (YYYY-MM-DD) |
| POST | `/api/ventas` | `{clienteId, items: [{productoId, cantidad}]}` |
| GET | `/api/ventas/{id}` | Ticket con sus renglones |
| GET | `/api/reportes/{ventas\|compras\|canjes}` | Reporte en JSON. Filtros: `desde`, `hasta`, `usuarioId` y según el tipo `clienteId`, `productoId`, `ciudadId`, `proveedorId`, `insumoId` |
| GET | `/api/reportes/{tipo}/excel` | El mismo reporte como archivo `.xlsx` |
| GET | `/api/usuarios` | Lista de usuarios (para filtrar reportes) |
| GET/POST | `/api/admin/usuarios` | Solo administradores: listar / crear usuarios `{nombre, email, rol, password}` |
| GET | `/api/admin/roles` | Solo administradores: roles disponibles |

Ventas, compras y canjes aceptan varios renglones por operación y se guardan en una sola transacción
(si un renglón falla no se guarda ninguno). Cada operación registra el usuario que la hizo
(columna `IDUsuario`; vale `null` en los registros anteriores a este cambio).

Errores: siempre JSON `{message}`; las validaciones devuelven 422 con `{message, errors: {campo: mensaje}}`.

## Estructura

```
app/config/         configuración (seguridad, rutas, servicios)
src/AppBundle/
  Entity/           entidades Doctrine (definen el esquema de la base)
  Controller/       un controlador por recurso
  Repository/       repositorios Doctrine (consultas DQL / QueryBuilder)
  Security/         usuario, JWT, autenticador y límite de intentos
  EventSubscriber/  CORS, errores JSON, mitigaciones
  Command/          comandos de consola para gestionar usuarios
sql/                esquema exportado, migración desde v1 y query de alta de usuario
web/app.php         punto de entrada
```
