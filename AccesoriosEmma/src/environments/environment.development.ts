// Configuración de desarrollo (ng serve).
// Las llamadas a /api las reenvía el proxy de Angular (proxy.conf.json) al
// backend Symfony en http://127.0.0.1:8000, levantado con:
//   cd BackendEmma && php bin/console server:run
export const environment = {
  production: false,
  apiUrl: '/api',
};
