# Emma Accesorios · Documentación técnica

Guía para desarrollar, mantener y desplegar el sistema. Para el uso diario de la aplicación ver
[MANUAL_USUARIO.md](MANUAL_USUARIO.md).

## Índice

1. [Arquitectura](#1-arquitectura)
2. [Tecnologías y versiones](#2-tecnologías-y-versiones)
3. [Puesta en marcha en desarrollo](#3-puesta-en-marcha-en-desarrollo)
4. [Backend (Symfony 3.4)](#4-backend-symfony-34)
5. [Modelo de datos y entidades](#5-modelo-de-datos-y-entidades)
6. [Base de datos: creación, cambios y migración](#6-base-de-datos-creación-cambios-y-migración)
7. [Seguridad y autenticación](#7-seguridad-y-autenticación)
8. [API REST](#8-api-rest)
9. [Procesos de negocio](#9-procesos-de-negocio)
10. [Frontend (Angular 20 + Material)](#10-frontend-angular-20--material)
11. [Recetas: cómo agregar cosas](#11-recetas-cómo-agregar-cosas)
12. [Despliegue en producción](#12-despliegue-en-producción)
13. [Deuda técnica y próximos pasos](#13-deuda-técnica-y-próximos-pasos)

---

## 1. Arquitectura

```
┌──────────────────────────┐   HTTPS + JSON    ┌───────────────────────────┐      ┌──────────────┐
│  Frontend (SPA)          │  Authorization:   │  Backend API REST         │ PDO  │  MariaDB /   │
│  Angular 20 + Material 3 │ ───────────────▶  │  Symfony 3.4 + Doctrine   │ ───▶ │  MySQL       │
│  AccesoriosEmma/         │   Bearer <JWT>    │  BackendEmma/             │      │ emmaaccesorios│
└──────────────────────────┘ ◀───────────────  └───────────────────────────┘      └──────────────┘
```

- El **frontend** es una SPA que solo habla con la API por HTTP/JSON. No tiene lógica de negocio
  crítica: precios, stock, ganancias y validaciones definitivas se resuelven en el backend.
- El **backend** es una API sin estado (*stateless*): no usa sesiones de PHP; cada petición se
  autentica con un token JWT.
- La **base de datos** se define a partir de las entidades de Doctrine (sección 5).

Estructura del repositorio:

```
EmmaAcesorios/
├── AccesoriosEmma/     Frontend Angular
├── BackendEmma/        Backend Symfony
├── docs/               Esta documentación y el manual de usuario
└── README.md           Puesta en marcha rápida
```

## 2. Tecnologías y versiones

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP | 7.4 oficial (probado también en 8.3); extensiones `pdo_mysql`, `zip`, `gd`, `xml`, `mbstring` |
| | Symfony (monolito `symfony/symfony`) | 3.4 LTS |
| | Doctrine ORM / DBAL | 2.7 / 2.13 |
| | DoctrineBundle | 1.12 |
| | StofDoctrineExtensionsBundle / gedmo/doctrine-extensions (Timestampable, SoftDeleteable) | 1.3 / 2.4 |
| | firebase/php-jwt | 6.x |
| Base de datos | MariaDB / MySQL | 10.4+ / 5.7+ |
| Frontend | Angular (standalone components, signals) | 20 |
| | Angular Material / CDK (Material Design 3) | 20 |
| | jsPDF (tickets en PDF) | 4 |
| Reportes | phpoffice/phpspreadsheet (archivos .xlsx) | 1.30 |
| | TypeScript | 5.9 |

`composer.json` fija la plataforma en PHP 7.4.33 (`config.platform.php`) para que Composer resuelva
siempre las mismas versiones, sin importar el PHP de la máquina.

## 3. Puesta en marcha en desarrollo

### Backend

```bash
cd BackendEmma
composer install
cp .env.dist .env                      # completar los valores (ver tabla)
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console app:usuario:crear tu@email.com "Tu nombre" --admin
php bin/console server:run             # http://127.0.0.1:8000
```

Variables de entorno (`BackendEmma/.env`, **nunca** se sube al repo):

| Variable | Descripción | Ejemplo |
|---|---|---|
| `SYMFONY_ENV` | `dev` (errores detallados, `server:run`) o `prod` | `dev` |
| `DATABASE_URL` | Conexión a la base | `mysql://root:@127.0.0.1:3306/emmaaccesorios?serverVersion=mariadb-10.4.32&charset=utf8mb4` |
| `JWT_SECRET` | Clave para firmar los tokens (≥ 32 caracteres aleatorios) | `php -r "echo bin2hex(random_bytes(32));"` |
| `JWT_TTL` | Duración del token en segundos | `28800` (8 h) |
| `CORS_ALLOW_ORIGIN` | Regex de orígenes que pueden llamar a la API | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` |

Si `JWT_SECRET` falta o es corto, la API responde error en todas las rutas protegidas (falla cerrada
a propósito).

### Frontend

```bash
cd AccesoriosEmma
npm install
npm start                              # http://localhost:4200
```

En desarrollo `apiUrl` es `/api` (`src/environments/environment.development.ts`) y el **proxy de
Angular** (`proxy.conf.json`, configurado en `angular.json` → `serve.options.proxyConfig`) reenvía
esas peticiones a `http://127.0.0.1:8000`. Así no hace falta CORS en desarrollo. Si cambiás el puerto
del backend, cambialo en `proxy.conf.json` y reiniciá `npm start`.

> Si la consola del navegador muestra errores `HttpErrorResponse` con `status: 200` y `ok: false`,
> la petición recibió HTML en lugar de JSON: `apiUrl` apunta al servidor de Angular sin proxy, o el
> proxy no está activo. Si muestra *"No se pudo conectar con el servidor"*, el backend no está corriendo.

### Comandos útiles

| Comando | Para qué |
|---|---|
| `php bin/console debug:router` | Lista todas las rutas de la API |
| `php bin/console doctrine:schema:validate` | Verifica que entidades y base coincidan |
| `php bin/console doctrine:schema:update --dump-sql` | Muestra el SQL pendiente para sincronizar la base |
| `php bin/console cache:clear` | Limpia la caché (necesario en `prod` después de cambiar config) |
| `php bin/console server:start` / `server:stop` | Servidor de desarrollo en segundo plano (Linux/macOS) |
| `npx ng build` | Compila el frontend para producción en `dist/` |

## 4. Backend (Symfony 3.4)

### Estructura

```
BackendEmma/
├── app/
│   ├── AppKernel.php          Bundles registrados (Framework, Security, Monolog, Doctrine,
│   │                          StofDoctrineExtensions, AppBundle; WebServerBundle solo en dev)
│   ├── autoload.php           Autoload de Composer + carga de .env
│   └── config/
│       ├── config.yml         Framework, Doctrine (DBAL + ORM + filtro softdeleteable), Gedmo, Monolog
│       ├── config_{dev,prod,test}.yml
│       ├── services.yml       Autowiring de todo src/AppBundle (excepto Entity)
│       ├── security.yml       Encoder bcrypt, provider de entidad, firewalls
│       ├── routing.yml        Rutas (auth, dashboard, operaciones)
│       └── routing/*.yml      Rutas CRUD de cada catálogo
├── bin/console
├── src/AppBundle/
│   ├── Entity/                Entidades Doctrine (= esquema de la base)
│   ├── Repository/            Consultas (QueryBuilder / DQL) e IncluyeBorrados (historial)
│   ├── Controller/            Un controlador por recurso
│   ├── Security/              JWT, autenticador Guard, límite de intentos por IP
│   ├── Service/               Reportes (datos) y ExcelReporte (archivo .xlsx)
│   ├── EventSubscriber/       CORS, errores en JSON, mitigación PATH_INFO
│   └── Command/               Comandos de consola para usuarios
├── sql/                       Esquema exportado, migraciones v1→v2 y v2→v3, alta de usuario por SQL
├── var/                       Caché y logs (no versionado)
└── web/                       Document root: app.php + .htaccess
```

### Ciclo de una petición

1. `web/app.php` carga el autoload y el `.env`, crea el `AppKernel` y procesa la petición.
2. `PathInfoSubscriber` (prioridad 512) rechaza rutas mal formadas.
3. `CorsSubscriber` (prioridad 250) responde los *preflight* `OPTIONS` antes de la seguridad.
4. El **firewall** `api` ejecuta `JwtAuthenticator`: lee `Authorization: Bearer …`, valida el token y
   carga el `Usuario`. Sin token válido responde **401**.
5. El **router** elige el controlador (rutas en YAML, `app/config/routing*.yml`).
6. El controlador valida, usa repositorios / `EntityManager` y devuelve un `JsonResponse`.
7. `ApiExceptionSubscriber` convierte cualquier excepción en JSON (`{message}`) sin filtrar detalles
   internos; los errores 500 se registran en `var/logs/`.
8. `CorsSubscriber` agrega los headers CORS a la respuesta si el origen está permitido.

### Controladores

Todos extienden `ApiController`, que aporta:

| Método | Uso |
|---|---|
| `getJson($request)` | Decodifica el cuerpo; si no es JSON válido lanza 400 |
| `validarEntidad($entidad, $alias)` | Valida las anotaciones `@Assert` de la entidad → 422 con errores por campo |
| `validar($data, $reglas)` | Valida un array (forma del request, renglones de operaciones múltiples) |
| `renglones($campos)` | Regla para listas de ítems (`items: [...]`), mínimo 1 y máximo 100 |
| `ordenarPor($items, $campo)` | Ordena renglones por id para bloquear filas en orden fijo |
| `valor($data, $campo, $default)` | Lee un campo opcional y recorta espacios |
| `error($msg, $status)` / `errorDeCampo($campo, $msg)` / `noEncontrado()` | Respuestas de error |
| `$this->em` | `EntityManager` de Doctrine |
| `$this->getUser()` | Usuario autenticado (entidad `Usuario`) |

Patrón típico de alta/edición (ver `ProductoController::guardar`): leer JSON → setear en la entidad →
`validarEntidad` → `persist` + `flush` → devolver `toArray()`.

### Repositorios

Extienden `ServiceEntityRepository` y se inyectan por autowiring. Convenciones:

- `listar(...)`: listados con filtros y `JOIN` de las relaciones que se muestran (evita N+1).
- `buscar($id)`: `find` normal; como el filtro de borrados está activo, nunca devuelve un registro
  borrado.
- Listados **históricos** (`TicketRepository`, `CompraRepository`, `CanjeRepository`,
  `Service/Reportes`) se ejecutan con `IncluyeBorrados::ejecutar($em, fn)`, que desactiva el filtro
  durante la consulta, para seguir mostrando clientes, productos o proveedores dados de baja.
- `buscarParaActualizarStock($id)`: `find` con `LockMode::PESSIMISTIC_WRITE` (`SELECT … FOR UPDATE`).
  Usar **siempre dentro de una transacción**.
- Agregados (totales, cantidades) con subconsultas DQL en el `SELECT` para no depender de
  `ONLY_FULL_GROUP_BY`.

### Comandos de consola

| Comando | Descripción |
|---|---|
| `app:usuario:crear <email> <nombre> [--admin] [--preguntar]` | Crea un usuario. Sin `--preguntar` genera una contraseña aleatoria de 20 caracteres y la muestra una sola vez |
| `app:usuario:password <email> [--preguntar]` | Nueva contraseña; además desbloquea la cuenta |

## 5. Modelo de datos y entidades

Las entidades están en `src/AppBundle/Entity` y se mapean con **anotaciones**. Las tablas y columnas
conservan los nombres de la versión original (por eso hay mezcla de estilos, p. ej. `NombreProducto`,
`stockProducto`), pero en PHP y en la API las propiedades usan nombres limpios en camelCase.

### Diagrama

```
Ciudad 1 ──── n Cliente 1 ──── n Ticket 1 ──── n Venta n ──── 1 Producto
   │                               │
   1                               n ──── 1 Usuario
   │
   n
Proveedor 1 ──── n Compra n ──── 1 Insumo
   │                  └─ n ──── 1 Usuario
   │
   1 ──── n Canje n ──── 1 Producto
               ├── n ──── 1 Insumo
               └── n ──── 1 Usuario
```

(`1 ── n` = uno a muchos. Por ejemplo, un cliente tiene muchos tickets y cada ticket es de un cliente.)

Además de los campos de la tabla, **todas** las entidades tienen `created_at`, `updated_at` y
`deleted_at` (ver *Timestamps y borrado lógico*).

### Detalle por entidad

| Entidad (tabla) | Campos principales (propiedad → columna) | Relaciones | Notas |
|---|---|---|---|
| **Ciudad** (`ciudad`) | `nombre`→`NombreCiudad`, `provincia`→`Provincia` (int) | — | Provincias en la constante `Ciudad::PROVINCIAS` |
| **Cliente** (`cliente`) | `nombre`→`nombreCliente`, `telefono`→`telefonoCliente` | `ciudad` → Ciudad (nullable) | |
| **Proveedor** (`proveedores`) | `nombre`, `telefono`→`TelefonoProveedor` | `ciudad` → Ciudad (nullable) | |
| **Producto** (`producto`) | `nombre`→`NombreProducto`, `stock`→`stockProducto`, `precio`→`PrecioProducto`, `coste`→`costeProduccion` | — | `descontarStock()` lanza `DomainException` si no alcanza |
| **Insumo** (`insumo`) | `nombre`→`NombreInsumo`, `stock`→`Stock`, `precio`, `descuentoCanje`→`DescuentoPactadoCanje` (%) | — | `sumarStock()` |
| **Ticket** (`ticket`) | `fecha`→`Fecha`, `cantidadProductos`→`CProductos`, `total`→`Valor` | `cliente` → Cliente, `usuario` → Usuario, `items` → Venta[] (1:n, cascade persist) | Una venta completa. `agregarProducto()` crea el renglón y descuenta stock |
| **Venta** (`venta`) | `cantidad`→`CantidadProducto`, `precioUnitario`, `profit`, `fecha`→`fechaVenta`, `total`→`Total` | `ticket`, `cliente`, `producto` | Renglón de un ticket. Guarda el precio del momento |
| **Compra** (`compras`) | `fecha`→`FechaCompra`, `cantidad`, `costo` | `proveedor`, `insumo`, `usuario` | Un renglón por insumo comprado |
| **Canje** (`canjes`) | `fecha`→`FechaCanje`, `cantidadProducto`, `cantidadInsumo`, `profit`→`Profit` | `proveedor`, `producto`, `insumo`, `usuario` | `calcularProfit()` |
| **Usuario** (`usuario`) | `nombre`, `email`→`UsuarioEmail` (único), `password`→`PasswordHash`, `rol`, `activo`, `intentosFallidos`, `bloqueadoHasta`, `ultimoLogin` | — | Implementa `AdvancedUserInterface`. Roles en `Usuario::ROLES` (1 = Administrador, 2 = Usuario) |

Los montos son `DECIMAL(12,2)`; Doctrine los devuelve como string y los getters los convierten a
`float`. El campo `usuario` de Ticket/Compra/Canje es `null` en registros anteriores a su creación.

### Timestamps y borrado lógico (todas las entidades)

Todas las entidades usan los traits de Gedmo:

```php
/**
 * @ORM\Entity(...)
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Producto
{
    use TimestampableEntity;   // created_at, updated_at (se completan solos)
    use SoftDeleteableEntity;  // deleted_at (NULL = activo)
```

- `created_at` / `updated_at` los completa Gedmo al insertar y al modificar.
- **Todos los borrados son soft delete**: `$em->remove($entidad); $em->flush();` no ejecuta un `DELETE`,
  sino que guarda la fecha en `deleted_at`. `hardDelete=false` hace que borrar dos veces tampoco elimine
  la fila.
- El filtro Doctrine `softdeleteable` (activo siempre, `config.yml`) agrega `deleted_at IS NULL` a todas
  las consultas: los borrados no aparecen en catálogos, búsquedas, `find()` ni en el login (un usuario
  borrado no puede ingresar).
- Los listados históricos lo desactivan con `IncluyeBorrados` (ver Repositorios).
- Para recuperar un registro borrado: `UPDATE tabla SET deleted_at = NULL WHERE ...`.
- Ciudades: no se pueden borrar mientras tengan clientes o proveedores **activos** (409). El resto se
  puede borrar siempre: el historial no se pierde.

## 6. Base de datos: creación, cambios y migración

### Instalación nueva

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console doctrine:schema:validate
```

`sql/schema.sql` es el mismo esquema exportado (`doctrine:schema:create --dump-sql`) para quien
prefiera importarlo desde phpMyAdmin. **Se regenera, no se edita a mano.**

### Cambiar el modelo

1. Modificar la entidad (agregar propiedad + anotaciones `@ORM\Column` y `@Assert`).
2. Backup de la base.
3. `php bin/console doctrine:schema:update --dump-sql` → **leer** el SQL (ojo con `DROP`).
4. `php bin/console doctrine:schema:update --force`.
5. `php bin/console doctrine:schema:validate`.
6. Regenerar `sql/schema.sql`:
   ```bash
   php bin/console doctrine:schema:create --dump-sql
   ```
   y reemplazar las sentencias `CREATE/ALTER` del archivo.

> Si el proyecto crece, conviene sumar **DoctrineMigrationsBundle** (versión 2.x para Symfony 3.4)
> para versionar cada cambio de esquema en vez de usar `schema:update`.

### Migración desde la base original (v1)

`sql/migracion_v1_a_v2.sql` hace solo lo que Doctrine no puede hacer sin perder datos:

- Convierte claves guardadas como `VARCHAR` a `INT` (limpiando valores inválidos).
- Pasa montos de `INT` a `DECIMAL(12,2)`.
- Crea la tabla `ticket` y un ticket por cada venta vieja que no tenía.
- Renombra `PaswordUsuario` → `PasswordHash` e **invalida** las contraseñas en texto plano.

Después, `doctrine:schema:update --force` agrega claves foráneas, índices, la columna `IDUsuario` y
**elimina las columnas de imágenes**. Pasos completos en `BackendEmma/README.md`.

El dump original queda como referencia en `sql/emmaaccesorios_v1_original.sql`.

### Migración v2 → v3 (timestamps y soft delete)

Para bases que ya estaban en v2 (o recién migradas desde v1), **antes** de `schema:update`:

```bash
mysqldump -u root emmaaccesorios > backup_antes_v3.sql
mysql -u root emmaaccesorios < sql/migracion_v2_a_v3.sql
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force
php bin/console doctrine:schema:validate
```

El script agrega `created_at`, `updated_at` y `deleted_at` con datos reales (las ventas, compras y
canjes toman su propia fecha como `created_at`), pasa lo que estaba oculto con `visibility = 0` a
`deleted_at` y renombra `usuario.FechaCreacion` a `created_at`. Sin este paso, `schema:update`
borraría `visibility` perdiendo qué registros estaban dados de baja y cargaría fechas `0000-00-00`.
Usa sintaxis de MariaDB (`ADD COLUMN IF NOT EXISTS`, `CHANGE COLUMN IF EXISTS`).

## 7. Seguridad y autenticación

### Flujo de login

```
Frontend                         Backend                                   Base
   │  POST /api/login {email,pwd}   │                                        │
   │ ─────────────────────────────▶ │ ¿IP superó 20 intentos/15 min? → 429   │
   │                                │ buscar usuario por email ─────────────▶│
   │                                │ ¿bloqueado? → 423                      │
   │                                │ verificar bcrypt (o hash falso si no   │
   │                                │ existe, mismo tiempo de respuesta)     │
   │                                │ falla → +1 intento (5 = bloqueo 15')   │
   │ ◀───────────────────────────── │ ok → {token, expiraEn, usuario}        │
   │  GET /api/... Authorization: Bearer <token>                             │
```

| Medida | Dónde |
|---|---|
| Contraseñas con **bcrypt** (cost 12) | `security.yml` → `encoders` |
| Token **JWT HS256** firmado con `JWT_SECRET`, emisor fijo y vencimiento (`JWT_TTL`) | `Security/JwtManager.php` |
| Validación del token en cada petición; usuario inactivo → 401 | `Security/JwtAuthenticator.php` |
| Bloqueo de cuenta: 5 fallos → 15 minutos | `Entity/Usuario::registrarLoginFallido()` |
| Límite por IP: 20 fallos cada 15 minutos (caché `cache.app`) | `Security/LoginThrottle.php` |
| Mensaje genérico "usuario o contraseña incorrectos" y tiempo constante | `Controller/AuthController.php` |
| CORS solo para `CORS_ALLOW_ORIGIN` | `EventSubscriber/CorsSubscriber.php` |
| Errores sin detalles internos; `X-Content-Type-Options` y `Cache-Control: no-store` | `EventSubscriber/ApiExceptionSubscriber.php` |
| Consultas parametrizadas (Doctrine) → sin inyección SQL | Repositorios |
| Mitigación CVE-2025-64500 (PATH_INFO) | `EventSubscriber/PathInfoSubscriber.php` |

Del lado del frontend el token se guarda en `sessionStorage` (se borra al cerrar el navegador), se
agrega en cada petición con un interceptor y, al vencer o recibir 401, se cierra la sesión.

Roles: `ROLE_ADMIN` (rol 1) y `ROLE_USER` (rol 2), definidos en `Usuario::ROLES` y
`Usuario::getRoles()`.

- `/api/admin/*` (Configuración) exige `ROLE_ADMIN` en `access_control` de `security.yml`; un usuario
  de rol 2 recibe **403**.
- En el frontend, `AuthService.esAdmin()` oculta el grupo *Configuración* del menú y `adminGuard`
  protege la ruta `/configuracion` (redirige a Inicio). Es solo comodidad: la seguridad real es la del
  backend.
- El resto de las pantallas está disponible para ambos roles. Para restringir algo más, sumar reglas en
  `access_control` y, si corresponde, ocultarlo en el menú.
- Para agregar un rol: sumarlo a `Usuario::ROLES`, mapearlo en `getRoles()` y, si hereda permisos,
  en `role_hierarchy` de `security.yml`. Aparece solo en el formulario de Configuración.

## 8. API REST

Base: `/api`. Todas las rutas salvo `/api/login` requieren `Authorization: Bearer <token>`.
Cuerpos y respuestas en JSON.

| Método | Ruta | Cuerpo / parámetros | Respuesta |
|---|---|---|---|
| POST | `/login` | `{email, password}` | `{token, expiraEn, usuario}` |
| GET | `/me` | — | Usuario actual |
| POST | `/me/password` | `{actual, nueva}` (nueva ≥ 12 caracteres) | `{message}` |
| GET | `/dashboard` | — | Totales del mes y productos con stock ≤ 5 |
| GET | `/productos` | `?q=` | `[{id, nombre, stock, precio, coste, ganancia}]` |
| POST · PUT `/{id}` · DELETE `/{id}` | `/productos` | `{nombre, stock, precio, coste}` | Producto / 204 |
| GET · POST · PUT · DELETE | `/insumos` | `{nombre, stock, precio, descuentoCanje?}` | |
| GET · POST · PUT · DELETE | `/clientes` | `{nombre, ciudadId?, telefono?}` | Incluye `compras` y `totalComprado` |
| GET · POST · PUT · DELETE | `/proveedores` | `{nombre, ciudadId?, telefono?}` | |
| GET · POST · PUT · DELETE | `/ciudades` | `{nombre, provincia}` | DELETE → 409 si está en uso |
| GET | `/provincias` | — | `[{id, nombre}]` |
| GET | `/ventas` | `?clienteId&productoId&ciudadId&desde&hasta` (fechas `YYYY-MM-DD`) | Tickets |
| GET | `/ventas/{id}` | — | Ticket con `items` |
| POST | `/ventas` | `{clienteId, items: [{productoId, cantidad}]}` | Ticket creado |
| GET | `/compras` | `?proveedorId&insumoId` | Compras |
| POST | `/compras` | `{proveedorId, fecha?, items: [{insumoId, cantidad, costo}]}` | Lista de compras creadas |
| GET | `/canjes` | — | Canjes |
| POST | `/canjes` | `{proveedorId, descuentoProducto?, descuentoInsumo?, items: [{productoId, cantidadProducto, insumoId, cantidadInsumo}]}` | Lista de canjes creados |
| GET | `/reportes/{ventas\|compras\|canjes}` | Filtros (ver sección 9) | `{titulo, filtros, columnas, filas, totales, resumen}` |
| GET | `/reportes/{tipo}/excel` | Mismos filtros | Archivo `.xlsx` (`Content-Disposition: attachment`) |
| GET | `/usuarios` | — | `[{id, nombre}]` (para el filtro "Registró") |
| GET | `/admin/usuarios` | — (solo rol 1) | Usuarios con email, rol, estado, último ingreso y alta |
| POST | `/admin/usuarios` | `{nombre, email, rol, password}` (solo rol 1, password ≥ 12) | Usuario creado. 422 si el email ya existe (aunque esté borrado) |
| PUT | `/admin/usuarios/{id}` | `{nombre, email, rol}` | Usuario actualizado (422 si el email existe o si te quitás admin) |
| PUT | `/admin/usuarios/{id}/estado` | `{activo: true\|false}` | Usuario (409 si es tu usuario o el último admin activo) |
| PUT | `/admin/usuarios/{id}/password` | `{password}` (≥ 12) | Usuario; además lo desbloquea |
| DELETE | `/admin/usuarios/{id}` | — | 204, soft delete (409 si es tu usuario o el último admin activo) |
| GET | `/admin/roles` | — (solo rol 1) | `[{id, nombre}]` |

Códigos de estado:

| Código | Significado |
|---|---|
| 200 / 201 / 204 | OK / creado / borrado (soft delete) |
| 400 | JSON inválido o ruta mal formada |
| 401 | Sin token, token vencido o credenciales incorrectas |
| 403 | Sin permisos (p. ej. rol 2 en `/api/admin`) |
| 404 | Registro o ruta inexistente |
| 409 | Conflicto de negocio (sin stock, registro en uso) |
| 422 | Validación: `{message, errors: {campo: mensaje}}`. En renglones: `items.0.cantidad` |
| 423 | Cuenta bloqueada temporalmente |
| 429 | Demasiados intentos de login desde la IP |

Ejemplo con `curl`:

```bash
TOKEN=$(curl -s -H 'Content-Type: application/json' \
  -d '{"email":"tu@email.com","password":"..."}' http://127.0.0.1:8000/api/login | jq -r .token)
curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/productos
```

## 9. Procesos de negocio

### Venta (ticket)

1. Se recibe cliente + renglones. Los productos repetidos se suman en un solo renglón.
2. Se abre una transacción y se bloquean los productos **en orden de id** (evita *deadlocks*).
3. Por cada producto: se verifica stock, se descuenta y se crea la `Venta` con el **precio actual de la
   base** (nunca el que manda el navegador).
4. `profit` del renglón = `(precio − coste de producción) × cantidad`.
5. El ticket guarda cantidad total, total en pesos y el usuario que vendió.
6. Si algún producto no alcanza → *rollback* completo y **409** con el nombre del producto.

### Compra

- Un proveedor, una fecha y N renglones (insumo, cantidad, costo total del renglón).
- Cada renglón es una fila de `compras` y **suma** stock al insumo. Todo en una transacción.

### Canje

- Se entregan productos propios a un proveedor a cambio de insumos.
- Cada renglón **descuenta** stock del producto y **suma** stock del insumo.
- Ganancia del renglón:
  `precio insumo × (1 − desc. insumo %) × cant. insumo − precio producto × (1 − desc. producto %) × cant. producto`.
  Positiva = recibiste más valor del que entregaste.
- Todo o nada, igual que ventas y compras.

### Bajas

- **Todo borrado es soft delete** (`deleted_at`): el registro desaparece de catálogos, formularios y
  búsquedas, pero sigue en la base y en el historial (ventas, compras, canjes, reportes).
- Un producto o insumo borrado ya no se puede vender, comprar ni canjear; un cliente borrado ya no puede
  recibir ventas (la API responde que no existe).
- Ciudades: no se pueden borrar mientras tengan clientes o proveedores activos (409).

### Usuarios (Configuración)

- Solo los administradores (rol 1) ven la sección **Configuración** (`AdminUsuarioController`) y
  pueden **crear, editar** (nombre, email, rol), **restablecer la contraseña** (también desbloquea),
  **desactivar/activar** y **borrar** (soft delete) usuarios.
- Protecciones (en el backend; el frontend además oculta las acciones): nadie puede desactivarse,
  borrarse ni quitarse el rol de administrador a sí mismo, y siempre debe quedar al menos un
  administrador activo (`UsuarioRepository::contarAdminsActivos()`).
- Desactivar o borrar corta el acceso al instante: `JwtAuthenticator` recarga el usuario en cada
  petición, así que el token que ya tenía deja de valer (401).
- Login de un usuario inactivo: si la contraseña es correcta responde **403** "Tu usuario está
  desactivado"; si no, el 401 genérico (no revela nada a quien no conoce la contraseña).
- La contraseña se valida (mínimo 12 caracteres) y se guarda con bcrypt; el formulario puede generarla
  con `crypto.getRandomValues` y la muestra una única vez.
- El email se guarda en minúsculas y es único, incluso contra usuarios borrados.
- También se pueden crear por consola (`app:usuario:crear`).

### Reportes

Servicio `Service/Reportes.php` + `Controller/ReporteController.php` + pantalla `pages/reportes`.

| Reporte | Un renglón por | Filtros | Resumen (hoja 2 / paneles) |
|---|---|---|---|
| Ventas | producto vendido (renglón de ticket) | desde, hasta, cliente, producto, ciudad, usuario | por producto, cliente, ciudad y usuario (cuenta tickets distintos) |
| Compras | insumo comprado | desde, hasta, proveedor, insumo, usuario | por proveedor e insumo |
| Canjes | intercambio | desde, hasta, proveedor, producto, insumo, usuario | por proveedor, producto e insumo |

- Fechas en formato `YYYY-MM-DD`; los ids deben ser enteros positivos (si no → 422).
- `Reportes::generar()` arma los datos con QueryBuilder (consultas escalares, sin hidratar entidades)
  y describe los filtros en texto ("Desde 01/10/2026 · Cliente: …").
- `ExcelReporte::generar()` crea el `.xlsx` con PhpSpreadsheet:
  - Hoja **Detalle**: título, filtros, fecha y usuario que lo generó; encabezado con color de marca,
    fijo al hacer scroll y con **autofiltro**; fechas como fechas reales de Excel; formatos de moneda y
    números; fila **TOTAL** con `SUBTOTAL(109, …)`, que se recalcula al filtrar en Excel.
  - Hoja **Resumen**: indicadores y tablas agrupadas.
  - Impresión: A4 apaisado, ajustado al ancho, encabezado repetido y número de página.
  - Los textos que empiezan con `=`, `+`, `-` o `@` se guardan como texto (evita inyección de fórmulas).
- La vista previa del frontend usa el mismo JSON, así que lo que se ve es lo que se descarga.
- La descarga se hace con `HttpClient` (`responseType: 'blob'`) para enviar el token; el nombre del
  archivo sale del header `Content-Disposition`, expuesto por CORS (`Access-Control-Expose-Headers`).

Para agregar un reporte nuevo: un método más en `Reportes` (columnas, filas, totales, resumen), sumar
el tipo a `TIPOS`/`FILTROS` y a la ruta (`requirements`), y en el frontend agregar la pestaña y sus
filtros en `reportes-page.ts`. `ExcelReporte` no necesita cambios.

### Panel de inicio

Suma desde el día 1 del mes en curso: total y cantidad de tickets, ganancia de ventas, gasto en
compras, clientes y productos activos, y hasta 10 productos con stock ≤ 5.

## 10. Frontend (Angular 20 + Material)

### Estructura

```
AccesoriosEmma/
├── public/                  logoEmma.png (ícono/logo), factura.png (fondo del PDF)
├── src/
│   ├── environments/        apiUrl de producción y de desarrollo
│   ├── styles.scss          Tema Material 3 + utilidades globales
│   └── app/
│       ├── app.config.ts    Providers: router, HttpClient + interceptor, locale es-AR, defaults de Material
│       ├── app.routes.ts    Rutas (lazy loading por pantalla, guards)
│       ├── core/            Servicios y modelos
│       ├── shared/          Componentes reutilizables
│       ├── layout/          Estructura principal (menú + barra)
│       └── pages/           Una carpeta por pantalla
```

### `core/`

| Archivo | Responsabilidad |
|---|---|
| `models.ts` | Interfaces TypeScript de todo lo que devuelve la API |
| `api.service.ts` | Único punto de acceso HTTP. CRUD genérico (`listar`, `crear`, `actualizar`, `borrar` por `Recurso`) + métodos de operaciones |
| `auth.service.ts` | Login/logout, sesión en `sessionStorage`, signals `usuario()` y `autenticado()`, cierre automático al vencer |
| `auth.interceptor.ts` | Agrega `Authorization: Bearer` y hace logout ante un 401 |
| `auth.guard.ts` | `authGuard` (rutas privadas) e `invitadoGuard` (login) |
| `notificacion.service.ts` | Snackbars de éxito/error y helpers para leer errores del backend |

### `shared/`

| Componente | Qué hace |
|---|---|
| `DataTable` (`app-data-table`) | Tabla Material con búsqueda (sin acentos ni mayúsculas), orden por columna, paginado y menú Editar/Borrar o botón Ver. Acepta acciones extra por fila (`acciones: AccionFila<T>[]`, evento `accion`) y `borrable` para ocultar Borrar en algunas filas. Las columnas se configuran con `Columna<T>` (`tipo`: texto, numero, moneda, fecha, fechaHora, porcentaje; `alertaSi` pinta en rojo valores bajos) |
| `FormDialog` | Modal de alta/edición generado a partir de una lista de `CampoFormulario` (texto, número, entero, select, fecha, teléfono). Muestra en cada campo los errores 422 del backend |
| `CrudPage<T>` | Clase base de las pantallas ABM: carga el listado y abre los modales de alta, edición y borrado. Una pantalla nueva solo define `recurso`, `columnas`, `campos()` y `valoresDe()` |
| `ConfirmDialog` / `confirmar()` | Confirmación antes de borrar |
| `PageHeader` | Título, subtítulo y botones de la pantalla |
| `PaginadorEnCastellano` | Textos del paginador |

### Pantallas (`pages/`)

| Ruta | Componente | Tipo |
|---|---|---|
| `/login` | `LoginPage` | Formulario de ingreso |
| `/inicio` | `InicioPage` | Panel con indicadores |
| `/ventas` | `VentasPage` + `VentaDialog`, `TicketDialog`, `ticket-pdf.ts` | Operación con filtros |
| `/compras` | `ComprasPage` + `CompraDialog` | Operación múltiple |
| `/canjes` | `CanjesPage` + `CanjeDialog` | Operación múltiple |
| `/productos`, `/insumos`, `/proveedores`, `/ciudades`, `/clientes` | `*Page extends CrudPage` | ABM con `FormDialog` |
| `/clientes/:id` | `ClienteDetallePage` | Perfil + historial + nueva venta |
| `/reportes` | `ReportesPage` | Pestañas ventas/compras/canjes, filtros, vista previa y descarga Excel |
| `/configuracion` | `ConfiguracionPage` (`adminGuard`) + `RestablecerPasswordDialog` | Pestañas *Usuarios* (lista con editar, contraseña, activar/desactivar, borrar) y *Agregar usuario* |

Todas las rutas (salvo login) están bajo el componente `Shell` (`layout/shell.ts`), que arma el
*navigation drawer* lateral (fijo en escritorio, desplegable en celular) y el menú de usuario.

### Convenciones

- Componentes *standalone*, `inject()` en lugar de constructor, **signals** para estado y control flow
  `@if / @for` en las plantillas.
- Formularios reactivos tipados; los modales de operaciones usan `FormArray` para los renglones.
- Formato regional `es-AR` (moneda `$ 1.500,00`, fechas `dd/MM/yyyy`).
- Íconos: **Material Symbols Outlined** (Google Fonts).
- Tema en `styles.scss` con `mat.theme()` (paletas magenta + naranja, a partir del logo). En los
  componentes usar los tokens `var(--mat-sys-…)` en vez de colores fijos.
- jsPDF se importa de forma diferida (`import('jspdf')`) para no agrandar la carga inicial.

## 11. Recetas: cómo agregar cosas

### Un campo nuevo en un catálogo (ej. `codigo` en Producto)

**Backend**
1. `Entity/Producto.php`: propiedad con `@ORM\Column(name="Codigo", type="string", length=20, nullable=true)`
   y `@Assert\Length(max=20)`, getter/setter, y agregarlo en `toArray()`.
2. `Controller/ProductoController::guardar()`: `->setCodigo(self::valor($data, 'codigo'))`.
3. `doctrine:schema:update --dump-sql` / `--force` y regenerar `sql/schema.sql`.

**Frontend**
1. `core/models.ts`: `codigo: string | null` en `Producto`.
2. `pages/productos/productos-page.ts`: columna en `columnas`, campo en `campos()` y en `valoresDe()`.

### Un catálogo nuevo (ej. Categorías)

**Backend**
1. `Entity/Categoria.php` con `repositoryClass`, los traits `TimestampableEntity` y
   `SoftDeleteableEntity` y la anotación `@Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)`.
2. `Repository/CategoriaRepository.php` (extiende `ServiceEntityRepository`).
3. `Controller/CategoriaController.php` copiando el patrón de `CiudadController`.
4. `app/config/routing/categorias.yml` e importarlo en `routing.yml`.
5. `doctrine:schema:update`.

**Frontend**
1. Agregar `'categorias'` al tipo `Recurso` en `api.service.ts` y la interfaz en `models.ts`.
2. `pages/categorias/categorias-page.ts` + `.html` extendiendo `CrudPage` (copiar `ciudades`).
3. Ruta en `app.routes.ts` e ítem en el menú de `layout/shell.ts`.

### Una operación con stock

Seguir el patrón de `CompraController::crear`: validar forma con `validar()` + `renglones()`,
abrir transacción, bloquear con `buscarParaActualizarStock()` en orden de id (`ordenarPor`), modificar
entidades, `flush`, `commit`; ante `DomainException` → `rollback` y 409. Registrar
`->setUsuario($this->getUser())`.

## 12. Despliegue en producción

### Backend (Apache / XAMPP)

1. Subir `BackendEmma/` y correr `composer install --no-dev --optimize-autoloader`.
2. Crear `.env` con `SYMFONY_ENV=prod`, la base real, un `JWT_SECRET` nuevo y el dominio del frontend
   en `CORS_ALLOW_ORIGIN`.
3. El *DocumentRoot* (o un VirtualHost) debe apuntar a `BackendEmma/web/`, para que `.env`, `app/`,
   `src/` y `vendor/` **no** sean accesibles desde la web. Requiere `mod_rewrite` (`web/.htaccess`).
4. `php bin/console cache:clear --env=prod` y dar permisos de escritura a `var/`.
5. Base: usuario MySQL propio con permisos `SELECT, INSERT, UPDATE, DELETE` para la aplicación; los
   comandos `doctrine:*` se corren con un usuario administrador.
6. **HTTPS obligatorio**: el token viaja en cada petición.

### Frontend

1. Poner la URL real de la API en `src/environments/environment.ts`.
2. `npx ng build` (si va en una subcarpeta: `--base-href /carpeta/`).
3. Subir `dist/AccesoriosEmma/browser/` al servidor web y redirigir las rutas desconocidas a
   `index.html` (en Apache: `FallbackResource /index.html`).

## 13. Deuda técnica y próximos pasos

- **Symfony 3.4 está fuera de soporte** desde noviembre de 2021 y `composer audit` reporta
  vulnerabilidades. La mayoría afecta componentes no usados (Twig, Mailer, X509); la que aplica tiene
  mitigación. Migrar a **Symfony 6.4 / 7.x LTS** + Doctrine ORM 2.20/3 (requiere PHP 8.1+). Las
  entidades y repositorios se pueden llevar casi sin cambios (pasando de anotaciones a atributos PHP).
- **Doctrine ORM 2.7 en PHP 8**: funciona en las pruebas pero no está declarado oficialmente; en
  producción preferir PHP 7.4 mientras se siga en Symfony 3.4.
- **Tests automatizados**: hoy no hay. Prioridad sugerida: tests funcionales de la API para ventas,
  compras y canjes (stock y *rollback*), y del login (bloqueo).
- **Migraciones versionadas**: sumar DoctrineMigrationsBundle en lugar de `schema:update`.
- **Compras y canjes agrupados**: cada renglón es una fila independiente. Si se necesita ver/anular
  una compra completa, agregar una entidad cabecera (como `Ticket` en ventas).
- **Roles**: por ahora el rol solo restringe Configuración; falta definir qué puede hacer cada rol en el
  resto del sistema.
- **Papelera**: no hay pantalla para ver o restaurar registros borrados (se restauran por SQL).
- **Anulación de ventas** (devolver stock): no implementada.
- **Reportes muy grandes**: el Excel se arma en memoria; con decenas de miles de renglones conviene
  paginar la vista previa y generar el archivo en segundo plano.
