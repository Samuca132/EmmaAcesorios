# Emma Accesorios – Frontend

Angular 20 + [Angular Material](https://material.angular.dev/) (Material Design 3).

## Desarrollo

```bash
npm install
npm start            # http://localhost:4200
```

En desarrollo el frontend llama a `/api` y el proxy de Angular (`proxy.conf.json`) reenvía esas
peticiones al backend en `http://127.0.0.1:8000`, que se levanta con:

```bash
cd BackendEmma && php bin/console server:run
```

Si cambiás `proxy.conf.json` hay que reiniciar `npm start`. Si la aplicación muestra *"No se pudo
conectar con el servidor"*, el backend no está corriendo en ese puerto.

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
