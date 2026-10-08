# Emma Accesorios

Sistema de gestión (ventas, compras, canjes, stock y clientes) para Emma Accesorios.

| Carpeta | Contenido |
|---|---|
| [`BackendEmma/`](BackendEmma/README.md) | API REST en Symfony 3.4 + Doctrine ORM + MariaDB/MySQL |
| [`AccesoriosEmma/`](AccesoriosEmma/README.md) | Frontend Angular 20 con Angular Material (Material Design 3) |

## Documentación

- [Manual de usuario](docs/MANUAL_USUARIO.md): cómo usar el sistema día a día.
- [Documentación técnica](docs/DOCUMENTACION_TECNICA.md): arquitectura, rutas, entidades, base de datos,
  reglas de negocio, seguridad, despliegue y hallazgos.
- Versiones en PDF (con portada, índice paginado y diagramas): [`docs/pdf/`](docs/pdf/). Se regeneran con
  `docs/pdf/generar-pdf.mjs` (instrucciones al comienzo del script).
- [Documentación técnica anterior](docs/DESARROLLO.md): recetas y guía de despliegue (parcialmente
  desactualizada, ver hallazgos en la documentación técnica).

## Puesta en marcha rápida

```bash
# 1. Backend + base de datos
cd BackendEmma
composer install
cp .env.dist .env                         # completar DATABASE_URL y JWT_SECRET
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console app:usuario:crear admin@emmaaccesorios.com "Emma" --admin
php bin/console server:run                # http://127.0.0.1:8000

# 2. Frontend (en otra terminal)
cd AccesoriosEmma
npm install
npm start                                 # http://localhost:4200
```

Si ya tenías datos cargados con la versión anterior, en lugar de `doctrine:schema:create` seguí los
pasos de migración del [README del backend](BackendEmma/README.md#base-de-datos).
