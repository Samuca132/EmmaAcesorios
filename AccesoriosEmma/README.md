# Emma Accesorios – Frontend

Angular 20 + [Angular Material](https://material.angular.dev/) (Material Design 3).

## Desarrollo

```bash
npm install
npm start            # http://localhost:4200
```

Espera el backend en `http://localhost:8000/api` (ver `src/environments/environment.development.ts`).

## Producción

```bash
npm run build        # genera dist/AccesoriosEmma/browser
```

La URL de la API de producción se configura en `src/environments/environment.ts`.
Si se publica en Apache dentro de una subcarpeta, compilar con `npx ng build --base-href /carpeta/`
y agregar una regla que redirija las rutas desconocidas a `index.html`.

## Estructura

```
src/app/
  core/       servicios (API, autenticación), interceptor, guard, modelos
  shared/     tabla genérica, modal de formulario genérico, confirmación
  layout/     estructura principal (menú lateral + barra superior)
  pages/      una carpeta por pantalla
```

- Los listados usan `app-data-table` (búsqueda, orden por columna, paginado).
- Altas y ediciones se hacen en modales (`FormDialog`), sin cambiar de pantalla.
- La sesión se guarda en `sessionStorage` y se cierra sola cuando vence el token.
