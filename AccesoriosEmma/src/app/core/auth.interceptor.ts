import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

/**
 * Agrega el token a cada petición a la API y cierra la sesión si el
 * backend responde 401 (token vencido o inválido).
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const esApi = req.url.startsWith(environment.apiUrl);
  const esLogin = req.url === `${environment.apiUrl}/login`;
  const token = auth.token;

  if (esApi && !esLogin && token) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }

  return next(req).pipe(
    catchError((err: HttpErrorResponse) => {
      if (esApi && !esLogin && err.status === 401) {
        auth.logout('expirada');
      }
      return throwError(() => err);
    }),
  );
};
